<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ReviewImport;
use App\Services\Import\CsvImportService;
use App\Services\Import\ImportException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ReviewImportController extends Controller
{
    public function __construct(private readonly CsvImportService $csv) {}

    public function create(Product $product): Response
    {
        $this->authorize('update', $product);

        return Inertia::render('Imports/Create', [
            'product' => ['id' => $product->id, 'name' => $product->name],
            'limits' => [
                'max_rows' => config('reviewlens.import.max_rows'),
                'max_file_kb' => config('reviewlens.import.max_file_kb'),
                'min_chars' => config('reviewlens.import.min_review_chars'),
            ],
            'aliases' => CsvImportService::ALIASES,
        ]);
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);
        $request->validate([
            'file' => ['required', 'file', 'extensions:csv,txt', 'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel,text/x-csv', 'max:'.config('reviewlens.import.max_file_kb')],
            'source_name' => ['nullable', 'string', 'max:100'],
        ], [
            'file.max' => 'The file is larger than the '.(int) (config('reviewlens.import.max_file_kb') / 1024).' MB limit.',
            'file.extensions' => 'Only .csv files are accepted.',
            'file.mimetypes' => 'The file does not look like a CSV text file.',
        ]);

        $file = $request->file('file');
        $path = 'imports/'.$request->user()->id.'/'.Str::uuid().'.csv';
        $this->csv->disk()->put($path, file_get_contents($file->getRealPath()));

        try {
            [$headers] = $this->csv->readHeaders($this->csv->disk()->path($path));
        } catch (ImportException $e) {
            $this->csv->disk()->delete($path);

            return back()->withErrors(['file' => $e->getMessage()]);
        }

        $import = new ReviewImport;
        $import->forceFill([
            'user_id' => $request->user()->id, 'product_id' => $product->id,
            'original_filename' => Str::limit(basename($file->getClientOriginalName()), 200, ''),
            'stored_path' => $path, 'source_name' => $request->input('source_name'),
            'status' => 'previewed', 'headers' => $headers, 'mapping' => $this->csv->suggestMapping($headers),
        ])->save();

        return redirect()->route('imports.show', $import);
    }

    public function show(ReviewImport $import): Response|RedirectResponse
    {
        $this->authorize('view', $import);
        $import->load('product:id,name');

        $preview = null;
        $error = null;
        if ($import->status === 'previewed') {
            try {
                $preview = $this->csv->preview($import);
            } catch (ImportException $e) {
                $error = $e->getMessage();
            }
        }

        return Inertia::render('Imports/Show', [
            'importJob' => [
                'id' => $import->id, 'filename' => $import->original_filename, 'status' => $import->status,
                'headers' => $import->headers, 'mapping' => $import->mapping, 'source_name' => $import->source_name,
                'total' => $import->total_rows, 'accepted' => $import->accepted_rows, 'rejected' => $import->rejected_rows,
                'duplicates' => $import->duplicate_rows, 'row_errors' => $import->row_errors ?? [],
                'is_demo' => $import->is_demo, 'error' => $import->error,
            ],
            'product' => ['id' => $import->product->id, 'name' => $import->product->name],
            'preview' => $preview,
            'previewError' => $error,
            'fields' => CsvImportService::FIELDS,
        ]);
    }

    /** Re-run the preview with a different column mapping. */
    public function mapping(Request $request, ReviewImport $import): RedirectResponse
    {
        $this->authorize('view', $import);
        abort_unless($import->status === 'previewed', 409);
        $import->forceFill(['mapping' => $this->validatedMapping($request, $import)])->save();

        return redirect()->route('imports.show', $import);
    }

    public function confirm(Request $request, ReviewImport $import): RedirectResponse
    {
        $this->authorize('view', $import);
        abort_unless($import->status === 'previewed', 409);

        $import->forceFill(['mapping' => $this->validatedMapping($request, $import), 'status' => 'importing'])->save();
        try {
            $this->csv->import($import);
        } catch (ImportException $e) {
            $import->forceFill(['status' => 'failed', 'error' => $e->getMessage()])->save();
            $this->csv->discardFile($import);

            return redirect()->route('imports.show', $import)->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $import->forceFill(['status' => 'failed', 'error' => 'The import failed unexpectedly. Nothing further was processed.'])->save();
            $this->csv->discardFile($import);

            return redirect()->route('imports.show', $import)->with('error', 'The import failed unexpectedly.');
        }

        return redirect()->route('imports.show', $import)->with('success', 'Import finished.');
    }

    public function destroy(ReviewImport $import): RedirectResponse
    {
        $this->authorize('delete', $import);
        $product = $import->product_id;
        $this->csv->discardFile($import);
        // Remove the reviews this import added only when explicitly cancelling an unfinished one.
        if ($import->status !== 'completed') {
            $import->delete();
        } else {
            // Saved analyses would cite deleted reviews, so they are removed with them.
            \App\Models\AnalysisRun::query()->where('product_id', $product)->delete();
            $import->reviews()->delete();
            $import->delete();
        }

        return redirect()->route('products.show', $product)->with('success', 'Import discarded.');
    }

    private function validatedMapping(Request $request, ReviewImport $import): array
    {
        $data = $request->validate([
            'mapping' => ['required', 'array'],
            'mapping.*' => ['nullable', 'string', 'max:255'],
            'source_name' => ['nullable', 'string', 'max:100'],
        ]);
        $mapping = [];
        foreach (CsvImportService::FIELDS as $f) {
            $v = $data['mapping'][$f] ?? null;
            $mapping[$f] = ($v === '' || $v === null) ? null : $v;
        }
        try {
            $this->csv->assertMapping($mapping, $import->headers ?? []);
        } catch (ImportException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages(['mapping' => $e->getMessage()]);
        }
        $import->source_name = $data['source_name'] ?? $import->source_name;

        return $mapping;
    }
}
