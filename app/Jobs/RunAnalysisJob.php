<?php

namespace App\Jobs;

use App\Models\AnalysisRun;
use App\Services\Analysis\AnalysisPipeline;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class RunAnalysisJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $analysisRunId) {}

    public function handle(AnalysisPipeline $pipeline): void
    {
        $run = AnalysisRun::find($this->analysisRunId);
        if ($run) {
            $pipeline->run($run);
        }
    }

    /** Called when the worker itself crashes or times out. */
    public function failed(\Throwable $e): void
    {
        AnalysisRun::query()->whereKey($this->analysisRunId)
            ->whereIn('status', [AnalysisRun::STATUS_PENDING, AnalysisRun::STATUS_RUNNING])
            ->update([
                'status' => AnalysisRun::STATUS_FAILED,
                'error' => Str::limit('Background job failed: '.class_basename($e), 200),
                'finished_at' => now(),
            ]);
    }
}
