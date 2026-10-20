<?php

namespace App\Services\Nlp;

/**
 * Transparent baseline: lexicon scoring with negation, intensifiers and
 * contrast handling, combined with the star rating when present.
 * It is NOT a trained model. Confidence is qualitative (low/medium/high),
 * never a calibrated probability.
 */
final class LexiconSentimentClassifier implements SentimentClassifier
{
    private array $pos;

    private array $neg;

    private array $negations;

    private array $intens;

    private array $contrast;

    public function __construct(private readonly TextNormalizer $normalizer)
    {
        $this->pos = array_flip(Lexicon::POSITIVE);
        $this->neg = array_flip(Lexicon::NEGATIVE);
        $this->negations = array_flip(Lexicon::NEGATIONS);
        $this->intens = array_flip(Lexicon::INTENSIFIERS);
        $this->contrast = array_flip(Lexicon::CONTRAST);
    }

    /** @return array{score:float,pos:float,neg:float} */
    public function scoreText(string $text): array
    {
        $tokens = $this->normalizer->tokenize($text);
        $pos = 0.0;
        $neg = 0.0;
        $afterContrast = false;
        foreach ($tokens as $i => $tok) {
            if (isset($this->contrast[$tok])) {
                $afterContrast = true;

                continue;
            }
            $isPos = isset($this->pos[$tok]);
            $isNeg = isset($this->neg[$tok]);
            if (! $isPos && ! $isNeg) {
                continue;
            }
            $weight = 1.0;
            if ($i > 0 && isset($this->intens[$tokens[$i - 1]])) {
                $weight = 1.5;
            }
            if ($afterContrast) {
                $weight *= 1.25;
            }
            $negated = false;
            for ($k = max(0, $i - 3); $k < $i; $k++) {
                if (isset($this->negations[$tokens[$k]]) || str_ends_with($tokens[$k], "n't")) {
                    $negated = true;
                }
            }
            if ($negated) {
                [$isPos, $isNeg] = [$isNeg, $isPos];
                $weight *= 0.8;
            }
            if ($isPos) {
                $pos += $weight;
            } else {
                $neg += $weight;
            }
        }
        $score = ($pos - $neg) / ($pos + $neg + 1);

        return ['score' => round($score, 3), 'pos' => $pos, 'neg' => $neg];
    }

    public function labelFromRating(?int $rating): ?string
    {
        if ($rating === null) {
            return null;
        }

        return $rating >= 4 ? 'positive' : ($rating <= 2 ? 'negative' : 'neutral');
    }

    public function classify(string $text, ?int $rating): array
    {
        $s = $this->scoreText($text);
        $hits = $s['pos'] + $s['neg'];
        $ratingLabel = $this->labelFromRating($rating);

        $textLabel = null; // null = no usable text signal
        if ($hits >= 0.75) {
            if ($s['pos'] >= 1.0 && $s['neg'] >= 1.0 && min($s['pos'], $s['neg']) / max($s['pos'], $s['neg']) >= 0.6) {
                $textLabel = 'mixed';
            } elseif ($s['score'] >= 0.2) {
                $textLabel = 'positive';
            } elseif ($s['score'] <= -0.2) {
                $textLabel = 'negative';
            } else {
                $textLabel = 'neutral';
            }
        }

        if ($textLabel === null) {
            if ($ratingLabel === null) {
                return ['sentiment' => 'neutral', 'score' => 0.0, 'method' => 'none', 'confidence' => 'low', 'conflict' => false];
            }

            return [
                'sentiment' => $ratingLabel,
                'score' => $this->ratingScore($rating),
                'method' => 'rating',
                'confidence' => in_array($rating, [1, 5], true) ? 'medium' : 'low',
                'conflict' => false,
            ];
        }

        if ($ratingLabel === null) {
            return ['sentiment' => $textLabel, 'score' => $s['score'], 'method' => 'lexicon',
                'confidence' => $hits >= 3 ? 'medium' : 'low', 'conflict' => false];
        }

        $conflict = in_array($textLabel, ['positive', 'negative'], true)
            && in_array($ratingLabel, ['positive', 'negative'], true)
            && $textLabel !== $ratingLabel;

        if ($textLabel === 'neutral') {
            // weak text signal: defer to the rating, flag low confidence
            return ['sentiment' => $ratingLabel, 'score' => $this->ratingScore($rating), 'method' => 'lexicon+rating',
                'confidence' => 'low', 'conflict' => false];
        }

        $agree = $textLabel === $ratingLabel || $textLabel === 'mixed';
        $confidence = $conflict ? 'low' : ($agree && $hits >= 2 ? 'high' : 'medium');

        return ['sentiment' => $textLabel, 'score' => $s['score'], 'method' => 'lexicon+rating',
            'confidence' => $confidence, 'conflict' => $conflict];
    }

    private function ratingScore(?int $rating): float
    {
        return $rating === null ? 0.0 : round(($rating - 3) / 2, 3);
    }
}
