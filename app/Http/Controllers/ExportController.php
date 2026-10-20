<?php

namespace App\Http\Controllers;

use App\Models\AnalysisRun;
use App\Services\Export\AnalysisExporter;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function csv(AnalysisRun $analysis, string $type, AnalysisExporter $exporter): StreamedResponse
    {
        $this->authorize('export', $analysis);

        return $exporter->csv($analysis, $type);
    }

    public function report(AnalysisRun $analysis): Response
    {
        $this->authorize('export', $analysis);
        abort_unless($analysis->isCompleted(), 409, 'Only completed analyses can be exported.');

        $analysis->load('product:id,name,brand,category');
        $html = view('report', [
            'run' => $analysis,
            'themes' => $analysis->themes()->orderByDesc('mention_count')->get(),
            'findings' => $analysis->findings()->with('evidence.review:id,title,rating,reviewed_at,source,is_demo')->orderBy('position')->get(),
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="reviewlens-report-'.$analysis->id.'.html"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
