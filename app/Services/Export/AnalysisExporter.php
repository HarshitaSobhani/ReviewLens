<?php

namespace App\Services\Export;

use App\Models\AnalysisRun;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalysisExporter
{
    public const TYPES = ['summary', 'themes', 'reviews'];

    public function csv(AnalysisRun $run, string $type): StreamedResponse
    {
        abort_unless(in_array($type, self::TYPES, true), 404);
        abort_unless($run->isCompleted(), 409, 'Only completed analyses can be exported.');

        $filename = sprintf('reviewlens-analysis-%d-%s.csv', $run->id, $type);

        return response()->streamDownload(function () use ($run, $type) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads UTF-8
            match ($type) {
                'summary' => $this->summary($out, $run),
                'themes' => $this->themes($out, $run),
                'reviews' => $this->reviews($out, $run),
            };
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function put($out, array $row): void
    {
        fputcsv($out, CsvSanitizer::row($row), ',', '"', '');
    }

    private function summary($out, AnalysisRun $run): void
    {
        $this->put($out, ['finding_type', 'strength', 'origin', 'statement', 'evidence_review_ids', 'evidence_excerpts']);
        foreach ($run->findings()->with('evidence')->orderBy('position')->get() as $f) {
            $this->put($out, [
                $f->type, $f->strength, $f->origin, $f->statement,
                $f->evidence->pluck('review_id')->implode(' '),
                $f->evidence->pluck('excerpt')->implode(' || '),
            ]);
        }
    }

    private function themes($out, AnalysisRun $run): void
    {
        $this->put($out, ['theme', 'discovered', 'mentions', 'positive', 'negative', 'neutral_or_mixed', 'method', 'limitations']);
        foreach ($run->themes()->orderByDesc('mention_count')->get() as $t) {
            $this->put($out, [$t->label, $t->is_discovered, $t->mention_count, $t->positive_count, $t->negative_count,
                $t->neutral_count, $t->method, implode(' ', $t->limitations ?? [])]);
        }
    }

    private function reviews($out, AnalysisRun $run): void
    {
        $this->put($out, ['review_id', 'date', 'rating', 'inferred_sentiment', 'sentiment_method', 'confidence', 'rating_text_conflict', 'themes', 'source', 'is_demo_data', 'title', 'text']);
        $run->analysisReviews()->with(['review', 'themeLinks.theme:id,label'])->orderBy('review_id')
            ->chunkById(200, function ($chunk) use ($out) {
                foreach ($chunk as $ar) {
                    $r = $ar->review;
                    $this->put($out, [
                        $r->id, $r->reviewed_at?->format('Y-m-d'), $r->rating, $ar->sentiment, $ar->sentiment_method,
                        $ar->confidence, $ar->rating_text_conflict,
                        $ar->themeLinks->map(fn ($l) => $l->theme->label.' ('.$l->polarity.')')->implode('; '),
                        $r->source, $r->is_demo, $r->title, $r->body,
                    ]);
                }
            });
    }
}
