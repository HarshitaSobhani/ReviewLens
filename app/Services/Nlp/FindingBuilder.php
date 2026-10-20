<?php

namespace App\Services\Nlp;

/**
 * Stages 7-8: concise summary where every substantive claim carries evidence
 * (real review ids and verbatim excerpts taken from the stored review text).
 */
final class FindingBuilder
{
    /**
     * @param  ReviewResult[]  $results  keyed by review id
     * @param  array<string,array>  $themes
     * @param  array<string,mixed>  $stats
     * @return array<int,array{type:string,statement:string,strength:string,theme_key:?string,origin:string,evidence:array<int,array{review_id:int,excerpt:string}>}>
     */
    public function build(array $results, array $themes, array $stats, array $context = []): array
    {
        $max = (int) config('reviewlens.analysis.max_evidence_per_finding');
        $findings = [];
        $n = $stats['review_count'];

        // Overview
        $pct = fn ($c) => $n ? round($c / $n * 100) : 0;
        $sd = $stats['sentiment_distribution'];
        $avg = $stats['average_rating'] !== null
            ? sprintf(' Average star rating is %.1f from %d rated reviews.', $stats['average_rating'], $stats['rated_count'])
            : ' No star ratings were available.';
        $overview = sprintf(
            'Of %d analyzed reviews, %d%% read as positive, %d%% negative, %d%% mixed and %d%% neutral based on the inferred sentiment.%s',
            $n, $pct($sd['positive']), $pct($sd['negative']), $pct($sd['mixed']), $pct($sd['neutral']), $avg
        );
        $findings[] = [
            'type' => 'overview', 'statement' => $overview, 'strength' => $n >= 30 ? 'moderate' : 'weak',
            'theme_key' => null, 'origin' => 'heuristic',
            'evidence' => $this->overviewEvidence($results, $max),
        ];

        // Advantages & complaints & divided
        foreach ($themes as $key => $t) {
            $m = $t['mention_count'];
            $pos = $t['positive_count'];
            $neg = $t['negative_count'];
            $origin = $t['method'] === 'model' ? 'model' : 'heuristic';
            if ($t['method'] === 'frequency') {
                continue; // frequency-discovered terms may not be real aspects: listed as themes, never asserted as findings
            }

            if ($pos >= 2 && min($pos, $neg) >= 2 && min($pos, $neg) / max($pos, $neg) >= 0.4) {
                $findings[] = [
                    'type' => 'mixed', 'theme_key' => $key, 'origin' => $origin, 'strength' => 'weak',
                    'statement' => sprintf('Opinions on %s are divided: %d reviews are positive and %d negative among %d mentioning it. No conclusion is drawn.', $t['label'], $pos, $neg, $m),
                    'evidence' => array_merge(
                        $this->themeEvidence($results, $key, $t['review_ids']['positive'], 2),
                        $this->themeEvidence($results, $key, $t['review_ids']['negative'], 2),
                    ),
                ];

                continue;
            }
            if ($pos >= 2 && $pos > $neg) {
                $findings[] = $this->polarFinding('advantage', $t, $key, $pos, $m, $results, $origin, $max);
            } elseif ($neg >= 2 && $neg > $pos) {
                $findings[] = $this->polarFinding('complaint', $t, $key, $neg, $m, $results, $origin, $max);
            }
        }

        // Caveats
        foreach ($this->caveats($results, $stats, $context) as $c) {
            $findings[] = $c;
        }

        // Safety net: drop non-caveat claims lacking evidence.
        return array_values(array_filter($findings, fn ($f) => $f['type'] === 'caveat' || $f['evidence'] !== []));
    }

    private function polarFinding(string $type, array $t, string $key, int $count, int $mentions, array $results, string $origin, int $max): array
    {
        $share = $count / $mentions;
        $strength = match (true) {
            $count >= 5 && $share >= 0.7 => 'strong',
            $count >= 3 && $share >= 0.6 => 'moderate',
            default => 'weak',
        };
        $verb = $type === 'advantage' ? 'positive' : 'negative';
        $ids = $t['review_ids'][$verb];

        return [
            'type' => $type, 'theme_key' => $key, 'origin' => $origin, 'strength' => $strength,
            'statement' => sprintf(
                '%s is mentioned %s in %d of %d reviews that discuss it (%d%%).%s',
                $t['label'], $verb === 'positive' ? 'positively' : 'negatively', $count, $mentions, round($share * 100),
                $strength === 'weak' ? ' Evidence is limited.' : ''
            ),
            'evidence' => $this->themeEvidence($results, $key, $ids, $max),
        ];
    }

    private function themeEvidence(array $results, string $key, array $ids, int $limit): array
    {
        $ranked = array_filter(array_map(fn ($id) => $results[$id] ?? null, $ids));
        usort($ranked, function ($a, $b) use ($key) {
            $ca = ['high' => 2, 'medium' => 1, 'low' => 0][$a->confidence];
            $cb = ['high' => 2, 'medium' => 1, 'low' => 0][$b->confidence];

            return [$cb, $a->reviewId] <=> [$ca, $b->reviewId];
        });
        $out = [];
        foreach (array_slice($ranked, 0, $limit) as $r) {
            $out[] = ['review_id' => $r->reviewId, 'excerpt' => $this->excerpt($r, $r->themes[$key]['snippet'] ?? null)];
        }

        return $out;
    }

    private function overviewEvidence(array $results, int $max): array
    {
        $pick = [];
        foreach (['positive', 'negative', 'mixed', 'neutral'] as $label) {
            $c = array_filter($results, fn ($r) => $r->sentiment === $label);
            usort($c, fn ($a, $b) => [abs($b->score), $a->reviewId] <=> [abs($a->score), $b->reviewId]);
            if ($c) {
                $pick[] = $c[0];
            }
            if (count($pick) >= $max) {
                break;
            }
        }

        return array_map(fn ($r) => ['review_id' => $r->reviewId, 'excerpt' => $this->excerpt($r, null)], $pick);
    }

    private function caveats(array $results, array $stats, array $context): array
    {
        $caveats = [];
        $n = $stats['review_count'];
        $add = function (string $text, array $evidence = []) use (&$caveats) {
            $caveats[] = ['type' => 'caveat', 'statement' => $text, 'strength' => 'weak', 'theme_key' => null,
                'origin' => 'heuristic', 'evidence' => $evidence];
        };

        if ($n < 20) {
            $add("Only {$n} reviews were analyzed; patterns may not generalise.");
        }
        if ($stats['rated_count'] < $n) {
            $add(sprintf('%d of %d reviews have no star rating, so sentiment for those rests on text alone.', $n - $stats['rated_count'], $n));
        }
        if ($stats['rating_text_conflicts'] > 0) {
            $conf = array_slice(array_values(array_filter($results, fn ($r) => $r->conflict)), 0, 3);
            $add(sprintf('%d reviews have a star rating that contradicts their text sentiment (possible sarcasm, mis-rating, or lexicon error).', $stats['rating_text_conflicts']),
                array_map(fn ($r) => ['review_id' => $r->reviewId, 'excerpt' => $this->excerpt($r, null)], $conf));
        }
        if ($n > 0 && ($stats['confidence_distribution']['low'] / $n) > 0.5) {
            $add('More than half of the sentiment labels are low confidence. Confidence labels are qualitative, not calibrated probabilities.');
        }
        if (($context['skipped'] ?? 0) > 0) {
            $add(sprintf('%d records were skipped as unusable or beyond the analysis limit.', $context['skipped']));
        }
        if (($context['demo'] ?? false) === true) {
            $add('This analysis includes synthetic demonstration reviews, not genuine customer feedback.');
        }
        if ($n > 0 && $stats['average_rating'] !== null) {
            $top = ($stats['rating_distribution'][5] ?? 0) + ($stats['rating_distribution'][1] ?? 0);
            if ($top / max(1, $stats['rated_count']) > 0.85 && $stats['rated_count'] >= 10) {
                $add('Ratings are concentrated at the extremes (1 or 5 stars), which can indicate self-selected reviewers.');
            }
        }
        if (! empty($context['ai_fallback'])) {
            $add('The model-backed adapter was requested but unavailable ('.$context['ai_fallback'].'); the baseline engine was used.');
        }

        return $caveats;
    }

    private function excerpt(ReviewResult $r, ?string $snippet): string
    {
        $body = $r->body;
        if ($snippet !== null && $snippet !== '' && mb_strpos($body, $snippet) !== false) {
            return $snippet;
        }

        return mb_substr($body, 0, 240);
    }
}
