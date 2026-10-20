<?php

namespace App\Http\Controllers;

use App\Jobs\RunAnalysisJob;
use App\Models\AnalysisRun;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnalysisController extends Controller
{
    public static function summary(AnalysisRun $a): array
    {
        $s = $a->stats ?? [];

        return [
            'id' => $a->id, 'product_id' => $a->product_id, 'product_name' => $a->relationLoaded('product') ? $a->product?->name : null,
            'status' => $a->status, 'engine' => $a->engine, 'review_count' => $a->review_count,
            'average_rating' => $s['average_rating'] ?? null,
            'sentiment_distribution' => $s['sentiment_distribution'] ?? null,
            'created_at' => $a->created_at?->toIso8601String(), 'finished_at' => $a->finished_at?->toIso8601String(),
            'error' => $a->error, 'includes_demo' => (bool) ($a->provenance['includes_demo_data'] ?? false),
        ];
    }

    public function index(Request $request): Response
    {
        $request->validate(['status' => 'nullable|in:pending,running,completed,failed']);
        $runs = $request->user()->analyses()->with('product:id,name')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('id')->paginate(15)->withQueryString()
            ->through(fn ($a) => self::summary($a));

        return Inertia::render('Analyses/Index', ['analyses' => $runs, 'filters' => $request->only('status')]);
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);
        $data = $request->validate(['engine' => ['nullable', 'in:baseline,model']]);
        $engine = $data['engine'] ?? 'baseline';

        if ($product->reviews()->doesntExist()) {
            return back()->with('error', 'Import reviews before running an analysis.');
        }
        if ($product->analyses()->whereIn('status', ['pending', 'running'])->exists()) {
            return back()->with('error', 'An analysis for this product is already in progress.');
        }

        $run = $this->createRun($request, $product, $engine, null);

        return redirect()->route('analyses.show', $run)->with('success', 'Analysis queued.');
    }

    public function reanalyze(Request $request, AnalysisRun $analysis): RedirectResponse
    {
        $this->authorize('view', $analysis);
        $product = $analysis->product;
        if ($product->analyses()->whereIn('status', ['pending', 'running'])->exists()) {
            return back()->with('error', 'An analysis for this product is already in progress.');
        }
        $run = $this->createRun($request, $product, $analysis->config['engine'] ?? 'baseline', $analysis->id);

        return redirect()->route('analyses.show', $run)->with('success', 'Reanalysis queued. The previous result was kept.');
    }

    public function show(Request $request, AnalysisRun $analysis): Response
    {
        $this->authorize('view', $analysis);
        $analysis->load('product:id,name,category,brand');

        $payload = [
            'analysis' => self::summary($analysis) + [
                'product' => $analysis->product->only('id', 'name', 'category', 'brand'),
                'config' => $analysis->config, 'provenance' => $analysis->provenance, 'stats' => $analysis->stats,
                'pipeline_version' => $analysis->pipeline_version, 'dataset_hash' => $analysis->dataset_hash,
                'skipped_count' => $analysis->skipped_count, 'previous_run_id' => $analysis->previous_run_id,
                'started_at' => $analysis->started_at?->toIso8601String(),
            ],
        ];
        if ($analysis->isCompleted()) {
            $payload += $this->results($analysis);
        }

        return Inertia::render('Analyses/Show', $payload);
    }

    public function destroy(AnalysisRun $analysis): RedirectResponse
    {
        $this->authorize('delete', $analysis);
        $productId = $analysis->product_id;
        $analysis->delete();

        return redirect()->route('products.show', $productId)->with('success', 'Analysis deleted. Imported reviews were not affected.');
    }

    private function createRun(Request $request, Product $product, string $engine, ?int $previous): AnalysisRun
    {
        $run = new AnalysisRun;
        $run->forceFill([
            'user_id' => $request->user()->id, 'product_id' => $product->id, 'previous_run_id' => $previous,
            'status' => AnalysisRun::STATUS_PENDING, 'engine' => $engine,
            'pipeline_version' => config('reviewlens.pipeline_version'),
            'config' => ['engine' => $engine, 'min_theme_mentions' => config('reviewlens.analysis.min_theme_mentions'),
                'max_reviews' => config('reviewlens.analysis.max_reviews')],
        ])->save();
        RunAnalysisJob::dispatch($run->id);

        return $run;
    }

    /** @return array<string,mixed> */
    private function results(AnalysisRun $run): array
    {
        $themes = $run->themes()->orderByDesc('mention_count')->get()->map(fn ($t) => [
            'id' => $t->id, 'key' => $t->key, 'label' => $t->label, 'is_discovered' => $t->is_discovered,
            'mention_count' => $t->mention_count, 'positive' => $t->positive_count, 'negative' => $t->negative_count,
            'neutral' => $t->neutral_count, 'method' => $t->method, 'top_terms' => $t->top_terms, 'limitations' => $t->limitations,
        ]);

        $findings = $run->findings()->with(['evidence.review:id,title,body,rating,reviewed_at,source,is_demo,verified_purchase'])
            ->orderBy('position')->get()->map(fn ($f) => [
                'id' => $f->id, 'type' => $f->type, 'statement' => $f->statement, 'strength' => $f->strength,
                'origin' => $f->origin, 'theme_id' => $f->theme_id,
                'evidence' => $f->evidence->map(fn ($e) => [
                    'review_id' => $e->review_id, 'excerpt' => $e->excerpt, 'rating' => $e->review?->rating,
                    'date' => $e->review?->reviewed_at?->format('Y-m-d'), 'source' => $e->review?->source,
                    'is_demo' => (bool) $e->review?->is_demo, 'title' => $e->review?->title,
                ])->values(),
            ]);

        $examples = [];
        foreach (['positive' => 'desc', 'negative' => 'asc'] as $sent => $dir) {
            $examples[$sent] = $run->analysisReviews()->where('sentiment', $sent)->with('review:id,title,body,rating,reviewed_at,source,is_demo')
                ->orderBy('sentiment_score', $dir)->orderBy('review_id')->limit(3)->get()
                ->map(fn ($ar) => [
                    'review_id' => $ar->review_id, 'confidence' => $ar->confidence, 'method' => $ar->sentiment_method,
                    'title' => $ar->review->title, 'body' => mb_substr($ar->review->body, 0, 400), 'rating' => $ar->review->rating,
                    'date' => $ar->review->reviewed_at?->format('Y-m-d'), 'is_demo' => $ar->review->is_demo,
                ]);
        }

        return ['themes' => $themes, 'findings' => $findings, 'examples' => $examples];
    }
}
