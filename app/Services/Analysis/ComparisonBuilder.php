<?php

namespace App\Services\Analysis;

use App\Models\AnalysisRun;
use Illuminate\Support\Collection;

/**
 * Descriptive comparison of completed analyses. Deliberately never names a
 * "winner": sentiment and star averages alone cannot establish superiority.
 */
class ComparisonBuilder
{
    /** @param Collection<int,AnalysisRun> $runs */
    public function build(Collection $runs): array
    {
        $items = $runs->map(fn (AnalysisRun $r) => $this->item($r))->values();

        return [
            'items' => $items,
            'themes' => $this->themeTable($items),
            'differences' => $this->differences($items),
            'warnings' => $this->warnings($items),
            'verdict' => 'This comparison is descriptive. It does not declare a better product: review samples differ in size, source and reviewer self-selection.',
        ];
    }

    private function item(AnalysisRun $r): array
    {
        $s = $r->stats ?? [];
        $n = max(1, (int) ($s['review_count'] ?? 0));
        $sd = $s['sentiment_distribution'] ?? ['positive' => 0, 'negative' => 0, 'neutral' => 0, 'mixed' => 0];
        $themes = $r->themes()->get();
        $top = fn (string $col) => $themes->where($col, '>=', 2)->sortByDesc($col)->take(5)
            ->map(fn ($t) => ['key' => $t->key, 'label' => $t->label, 'count' => $t->{$col}, 'of' => $t->mention_count])->values();
        $summary = $r->findings()->whereIn('type', ['overview', 'advantage', 'complaint', 'mixed'])->orderBy('position')
            ->withCount('evidence')->get(['id', 'type', 'statement', 'strength'])
            ->map(fn ($f) => ['type' => $f->type, 'statement' => $f->statement, 'strength' => $f->strength, 'evidence_count' => $f->evidence_count]);

        return [
            'analysis_id' => $r->id, 'product_id' => $r->product_id, 'product_name' => $r->product->name,
            'review_count' => (int) ($s['review_count'] ?? 0), 'average_rating' => $s['average_rating'] ?? null,
            'rated_count' => $s['rated_count'] ?? 0, 'rating_distribution' => $s['rating_distribution'] ?? [],
            'sentiment_distribution' => $sd,
            'sentiment_pct' => array_map(fn ($c) => round($c / $n * 100, 1), $sd),
            'top_positive' => $top('positive_count'), 'top_negative' => $top('negative_count'),
            'summary' => $summary, 'date_range' => $s['date_range'] ?? null, 'sources' => $s['sources'] ?? [],
            'includes_demo' => (bool) ($r->provenance['includes_demo_data'] ?? false),
            'engine' => $r->provenance['engine'] ?? 'baseline', 'created_at' => $r->created_at->toIso8601String(),
        ];
    }

    private function themeTable(Collection $items): array
    {
        $keys = [];
        $labels = [];
        foreach ($items as $i => $item) {
            foreach (['top_positive', 'top_negative'] as $k) {
                foreach ($item[$k] as $t) {
                    $labels[$t['key']] = $t['label'];
                }
            }
        }
        $rows = [];
        foreach ($items as $item) {
            $run = AnalysisRun::find($item['analysis_id']);
            foreach ($run->themes as $t) {
                $keys[$t->key] ??= ['key' => $t->key, 'label' => $t->label, 'cells' => []];
                $keys[$t->key]['cells'][$item['analysis_id']] = [
                    'mentions' => $t->mention_count, 'positive' => $t->positive_count, 'negative' => $t->negative_count,
                    'neutral' => $t->neutral_count,
                    'share_of_reviews' => round($t->mention_count / max(1, $item['review_count']) * 100, 1),
                ];
            }
        }
        foreach ($keys as $row) {
            $rows[] = $row;
        }
        usort($rows, fn ($a, $b) => count($b['cells']) <=> count($a['cells']) ?: array_sum(array_column($b['cells'], 'mentions')) <=> array_sum(array_column($a['cells'], 'mentions')));

        return array_slice($rows, 0, 14);
    }

    /** Observed differences in recurring complaints (negative share ≥ 20 points apart). */
    private function differences(Collection $items): array
    {
        $out = [];
        $table = $this->themeTable($items);
        foreach ($table as $row) {
            if (count($row['cells']) < 2) {
                continue;
            }
            $shares = [];
            foreach ($row['cells'] as $runId => $c) {
                $shares[$runId] = $c['mentions'] > 0 ? $c['negative'] / $c['mentions'] * 100 : 0;
            }
            if (max($shares) - min($shares) >= 20) {
                $hi = array_search(max($shares), $shares);
                $lo = array_search(min($shares), $shares);
                $name = fn ($id) => $items->firstWhere('analysis_id', $id)['product_name'];
                $out[] = sprintf(
                    '%s: %s has a higher share of negative mentions (%d%% of %d) than %s (%d%% of %d).',
                    $row['label'], $name($hi), round($shares[$hi]), $row['cells'][$hi]['mentions'],
                    $name($lo), round($shares[$lo]), $row['cells'][$lo]['mentions']
                );
            }
        }

        return $out;
    }

    private function warnings(Collection $items): array
    {
        $w = [];
        $counts = $items->pluck('review_count');
        if ($counts->min() > 0 && $counts->max() / $counts->min() >= 3) {
            $w[] = sprintf('Review volumes differ a lot (%d vs %d). Percentages from the smaller sample are less stable.', $counts->min(), $counts->max());
        }
        if ($counts->min() < 20) {
            $w[] = 'At least one product has fewer than 20 analyzed reviews; differences may be noise.';
        }
        $sourceSets = $items->map(fn ($i) => collect($i['sources'])->sort()->implode('|'))->unique();
        if ($sourceSets->count() > 1) {
            $w[] = 'Reviews come from different or unspecified sources, so ratings may not be directly comparable.';
        }
        if ($items->pluck('includes_demo')->unique()->count() > 1) {
            $w[] = 'One analysis includes synthetic demonstration data and another does not.';
        } elseif ($items->first()['includes_demo']) {
            $w[] = 'These analyses use synthetic demonstration reviews, not genuine customer feedback.';
        }
        if ($items->pluck('engine')->unique()->count() > 1) {
            $w[] = 'The analyses used different engines (baseline vs model-backed); labels are not strictly comparable.';
        }
        $ranges = $items->pluck('date_range')->filter();
        if ($ranges->count() >= 2) {
            $starts = $ranges->pluck(0)->max();
            $ends = $ranges->pluck(1)->min();
            if ($starts > $ends) {
                $w[] = 'The review date ranges do not overlap, so the products were reviewed in different periods.';
            }
        }
        $cats = $items->map(fn ($i) => $i['product_id'])->unique()->count();
        if ($cats < $items->count()) {
            $w[] = 'The same product appears more than once (different analysis runs).';
        }

        return $w;
    }
}
