<?php

namespace App\Services\Nlp;

/** Stage 6: aggregate statistics. */
final class StatsCalculator
{
    /** @param ReviewResult[] $results */
    public function calculate(array $results, array $reviewMeta = []): array
    {
        $sent = ['positive' => 0, 'negative' => 0, 'neutral' => 0, 'mixed' => 0];
        $conf = ['low' => 0, 'medium' => 0, 'high' => 0];
        $dist = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        $methods = [];
        $sum = 0;
        $rated = 0;
        $conflicts = 0;
        foreach ($results as $r) {
            $sent[$r->sentiment]++;
            $conf[$r->confidence]++;
            $methods[$r->method] = ($methods[$r->method] ?? 0) + 1;
            if ($r->rating !== null) {
                $dist[$r->rating]++;
                $sum += $r->rating;
                $rated++;
            }
            if ($r->conflict) {
                $conflicts++;
            }
        }

        return [
            'review_count' => count($results),
            'rated_count' => $rated,
            'average_rating' => $rated ? round($sum / $rated, 2) : null,
            'rating_distribution' => $dist,
            'sentiment_distribution' => $sent,
            'confidence_distribution' => $conf,
            'method_counts' => $methods,
            'rating_text_conflicts' => $conflicts,
        ] + $reviewMeta;
    }
}
