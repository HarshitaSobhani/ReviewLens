<?php

namespace App\Services\Nlp;

/** Stage 6a: aggregate per-review aspect hits into theme statistics. */
final class ThemeAggregator
{
    /**
     * @param  ReviewResult[]  $results
     * @return array<string,array<string,mixed>> keyed by theme key, ordered by mention count desc
     */
    public function aggregate(array $results, int $minMentions): array
    {
        $themes = [];
        foreach ($results as $r) {
            foreach ($r->themes as $key => $t) {
                $themes[$key] ??= [
                    'key' => $key, 'label' => $t['label'], 'is_discovered' => $t['discovered'], 'method' => $t['method'],
                    'mention_count' => 0, 'positive_count' => 0, 'negative_count' => 0, 'neutral_count' => 0,
                    'terms' => [], 'review_ids' => ['positive' => [], 'negative' => [], 'neutral' => []],
                ];
                $themes[$key]['mention_count']++;
                $bucket = match ($t['polarity']) {
                    'positive' => 'positive', 'negative' => 'negative', default => 'neutral',
                };
                $themes[$key][$bucket.'_count']++;
                $themes[$key]['review_ids'][$bucket][] = $r->reviewId;
                foreach ($t['terms'] as $term) {
                    $themes[$key]['terms'][$term] = ($themes[$key]['terms'][$term] ?? 0) + 1;
                }
            }
        }

        $themes = array_filter($themes, fn ($t) => $t['mention_count'] >= $minMentions);
        foreach ($themes as &$t) {
            arsort($t['terms']);
            $t['top_terms'] = array_slice(array_keys($t['terms']), 0, 6);
            unset($t['terms']);
            $t['limitations'] = $this->limitations($t);
        }
        unset($t);
        uasort($themes, fn ($a, $b) => [$b['mention_count'], $a['key']] <=> [$a['mention_count'], $b['key']]);

        return $themes;
    }

    private function limitations(array $t): array
    {
        $l = [];
        if ($t['is_discovered']) {
            $l[] = 'Discovered by word frequency only; the term may be a generic word rather than a true product aspect.';
        }
        if ($t['mention_count'] < 5) {
            $l[] = 'Fewer than 5 mentions; treat as anecdotal.';
        }
        if ($t['positive_count'] > 0 && $t['negative_count'] > 0) {
            $l[] = 'Reviewers disagree on this aspect.';
        }
        if ($t['method'] === 'lexicon') {
            $l[] = 'Polarity comes from word lists applied to the sentence mentioning the aspect and can miss sarcasm or context.';
        }

        return $l;
    }
}
