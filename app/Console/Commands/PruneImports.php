<?php

namespace App\Console\Commands;

use App\Models\ReviewImport;
use App\Services\Import\CsvImportService;
use Illuminate\Console\Command;

class PruneImports extends Command
{
    protected $signature = 'reviewlens:prune-imports {--hours=24 : Age after which abandoned uploads are removed}';

    protected $description = 'Delete abandoned CSV uploads (never confirmed) and their stored files';

    public function handle(CsvImportService $csv): int
    {
        $count = 0;
        ReviewImport::query()->whereIn('status', ['previewed', 'importing', 'failed'])
            ->where('created_at', '<', now()->subHours((int) $this->option('hours')))
            ->each(function (ReviewImport $import) use ($csv, &$count) {
                $csv->discardFile($import);
                $import->delete();
                $count++;
            });
        $this->info("Removed {$count} abandoned import(s).");

        return self::SUCCESS;
    }
}
