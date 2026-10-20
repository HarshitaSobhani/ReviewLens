<?php

namespace App\Services\Nlp;

/**
 * Deterministic, explainable aspect assignment.
 * A review mentions an aspect when a clause contains one of the aspect's terms.
 * Polarity is the lexicon score of the clause containing the term.
 * Frequent unmatched terms are surfaced as "discovered" aspects.
 */
final class AspectExtractor
{
    private array $phraseIndex = [];

    private array $tokenIndex = [];

    private array $excluded;

    public function __construct(
        private readonly TextNormalizer $normalizer,
        private readonly LexiconSentimentClassifier $lexicon,
    ) {
        foreach (Lexicon::ASPECTS as $key => $def) {
            foreach ($def['terms'] as $term) {
                if (str_contains($term, ' ')) {
                    $this->phraseIndex[$term][] = $key;
                } else {
                    $this->tokenIndex[$term][] = $key;
                }
            }
        }
        $this->excluded = array_flip(array_merge(
            Lexicon::STOPWORDS, Lexicon::POSITIVE, Lexicon::NEGATIVE, Lexicon::NEGATIONS,
            Lexicon::INTENSIFIERS, array_keys($this->tokenIndex),
        ));
    }

    /** @return string[] */
    public function clauses(string $text): array
    {
        $clauses = [];
        foreach ($this->normalizer->sentences($text) as $sentence) {
            $parts = preg_split('/\s*;\s*|\s+(?:but|however|although|though|yet|except)\s+/iu', $sentence) ?: [$sentence];
            foreach ($parts as $p) {
                $p = trim($p);
                if ($p !== '') {
                    $clauses[] = $p;
                }
            }
        }

        return $clauses;
    }

    /**
     * Assign predefined aspects to one review.
     *
     * @return array<string,array{label:string,polarity:string,terms:string[],snippet:string,discovered:bool,method:string}>
     */
    public function extract(string $text): array
    {
        $found = [];
        foreach ($this->clauses($text) as $clause) {
            $lower = mb_strtolower($clause);
            $tokens = array_flip($this->normalizer->tokenize($clause));
            $matched = []; // aspectKey => terms
            foreach ($this->phraseIndex as $phrase => $keys) {
                if (preg_match('/\b'.preg_quote($phrase, '/').'\b/u', $lower)) {
                    foreach ($keys as $k) {
                        $matched[$k][] = $phrase;
                    }
                }
            }
            foreach ($tokens as $tok => $_) {
                foreach ($this->tokenIndex[$tok] ?? [] as $k) {
                    $matched[$k][] = $tok;
                }
            }
            if (! $matched) {
                continue;
            }
            $score = $this->lexicon->scoreText($clause)['score'];
            foreach ($matched as $key => $terms) {
                $found[$key]['clauses'][] = ['score' => $score, 'text' => $clause];
                $found[$key]['terms'] = array_values(array_unique(array_merge($found[$key]['terms'] ?? [], $terms)));
            }
        }

        $out = [];
        foreach ($found as $key => $data) {
            $out[$key] = $this->summarize(Lexicon::ASPECTS[$key]['label'], $data['clauses'], $data['terms'], false, 'lexicon');
        }

        return $out;
    }

    /**
     * Find frequent unmatched terms across a corpus and tag the reviews that mention them.
     *
     * @param  array<int,string>  $texts  reviewId => text
     * @return array<string,array<int,array>>  themeKey => [reviewId => themeData]
     */
    public function discover(array $texts): array
    {
        $n = count($texts);
        if ($n === 0) {
            return [];
        }
        $df = [];
        $starts = []; // times a term opens a sentence (typical of adverbs/openers, not aspects)
        $occ = [];
        foreach ($texts as $id => $text) {
            $seen = [];
            foreach ($this->normalizer->sentences($text) as $sentence) {
                foreach ($this->normalizer->tokenize($sentence) as $pos => $tok) {
                    $occ[$tok] = ($occ[$tok] ?? 0) + 1;
                    if ($pos === 0) {
                        $starts[$tok] = ($starts[$tok] ?? 0) + 1;
                    }
                    if (mb_strlen($tok) < 4 || isset($this->excluded[$tok]) || is_numeric($tok) || isset($seen[$tok])
                        || str_ends_with($tok, 'ly')) {
                        continue;
                    }
                    $seen[$tok] = true;
                    $df[$tok] = ($df[$tok] ?? 0) + 1;
                }
            }
        }
        $df = array_filter($df, fn ($c, $tok) => ($starts[$tok] ?? 0) / max(1, $occ[$tok]) <= 0.5 && ($n < 10 || $c / $n <= 0.6), ARRAY_FILTER_USE_BOTH);
        $min = max((int) config('reviewlens.analysis.discover_min_reviews'), (int) ceil(0.08 * $n));
        $df = array_filter($df, fn ($c) => $c >= $min);
        arsort($df);
        $candidates = array_slice(array_keys($df), 0, 5, true);

        $out = [];
        foreach ($candidates as $term) {
            foreach ($texts as $id => $text) {
                $clauses = [];
                foreach ($this->clauses($text) as $clause) {
                    if (in_array($term, $this->normalizer->tokenize($clause), true)) {
                        $clauses[] = ['score' => $this->lexicon->scoreText($clause)['score'], 'text' => $clause];
                    }
                }
                if ($clauses) {
                    $out['discovered_'.preg_replace('/[^a-z0-9]+/', '_', $term)][$id] =
                        $this->summarize(ucfirst($term), $clauses, [$term], true, 'frequency');
                }
            }
        }

        return $out;
    }

    /** @param array<int,array{score:float,text:string}> $clauses */
    private function summarize(string $label, array $clauses, array $terms, bool $discovered, string $method): array
    {
        $pos = 0;
        $neg = 0;
        $best = $clauses[0];
        foreach ($clauses as $c) {
            if ($c['score'] >= 0.2) {
                $pos++;
            } elseif ($c['score'] <= -0.2) {
                $neg++;
            }
            if (abs($c['score']) > abs($best['score'])) {
                $best = $c;
            }
        }
        $polarity = match (true) {
            $pos > 0 && $neg > 0 && $pos === $neg => 'mixed',
            $pos > $neg => 'positive',
            $neg > $pos => 'negative',
            default => 'neutral',
        };

        return [
            'label' => $label,
            'polarity' => $polarity,
            'terms' => $terms,
            'snippet' => mb_substr($best['text'], 0, 400),
            'discovered' => $discovered,
            'method' => $method,
        ];
    }
}
