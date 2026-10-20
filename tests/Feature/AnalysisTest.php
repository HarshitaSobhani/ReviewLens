<?php

use App\Jobs\RunAnalysisJob;
use App\Models\AnalysisRun;
use App\Models\FindingEvidence;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\Analysis\AnalysisPipeline;
use App\Services\Demo\DemoDataService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->product = Product::factory()->for($this->user)->create();
});

function addReviews(Product $p, array $rows): void
{
    foreach ($rows as [$body, $rating]) {
        Review::factory()->for($p)->withBody($body)->create(['user_id' => $p->user_id, 'rating' => $rating]);
    }
}

function runAnalysis(Product $p, string $engine = 'baseline'): AnalysisRun
{
    $run = new AnalysisRun;
    $run->forceFill(['user_id' => $p->user_id, 'product_id' => $p->id, 'status' => 'pending', 'engine' => $engine,
        'pipeline_version' => '1.0.0', 'config' => ['engine' => $engine]])->save();
    app(AnalysisPipeline::class)->run($run);

    return $run->fresh();
}

function sampleReviews(): array
{
    return [
        ['Excellent quality and very durable, the battery lasts for days.', 5],
        ['Great battery life and the sound is fantastic.', 5],
        ['Battery is terrible, it drains fast and died in a week.', 1],
        ['The strap broke after a month, quality is poor and flimsy.', 1],
        ['Quality is poor, cracked in the first week, a waste of money.', 2],
        ['Delivery was quick and packaging was neat.', 4],
        ['Delivery was late and the box was damaged.', 2],
        ['Very comfortable and easy to set up, love the design.', 5],
        ['Nothing special but it works and the price is fair.', 3],
        ['Awful, broken and useless, total junk.', 5], // conflicting rating
    ];
}

it('runs the full pipeline and persists results with provenance', function () {
    addReviews($this->product, sampleReviews());
    $run = runAnalysis($this->product);

    expect($run->status)->toBe('completed')->and($run->review_count)->toBe(10)
        ->and($run->finished_at)->not->toBeNull()
        ->and($run->provenance['pipeline_version'])->toBe('1.0.0')
        ->and($run->provenance['ai']['requested'])->toBeFalse()
        ->and($run->dataset_hash)->toHaveLength(64)
        ->and($run->stats['rating_distribution'][5])->toBe(4)
        ->and($run->stats['rating_text_conflicts'])->toBeGreaterThanOrEqual(1)
        ->and($run->analysisReviews()->count())->toBe(10)
        ->and($run->themes()->where('key', 'quality_durability')->first()->mention_count)->toBeGreaterThanOrEqual(3);
});

it('only references real reviews of the same product as evidence', function () {
    $other = Product::factory()->for($this->user)->create();
    addReviews($other, [['Excellent quality, truly durable and sturdy build.', 5]]);
    addReviews($this->product, sampleReviews());
    $run = runAnalysis($this->product);

    $validIds = Review::where('product_id', $this->product->id)->pluck('id')->all();
    $evidence = FindingEvidence::whereIn('finding_id', $run->findings()->pluck('id'))->get();

    expect($evidence)->not->toBeEmpty();
    foreach ($evidence as $e) {
        expect($validIds)->toContain($e->review_id);
        // excerpts must be verbatim text from the cited review
        expect(str_contains(Review::find($e->review_id)->body, $e->excerpt))->toBeTrue();
    }
    // every non-caveat finding has evidence
    foreach ($run->findings()->where('type', '!=', 'caveat')->withCount('evidence')->get() as $f) {
        expect($f->evidence_count)->toBeGreaterThan(0);
    }
});

it('links theme findings to reviews with matching polarity', function () {
    addReviews($this->product, sampleReviews());
    $run = runAnalysis($this->product);
    $complaint = $run->findings()->where('type', 'complaint')->with('evidence')->get();
    expect($complaint)->not->toBeEmpty();
    foreach ($complaint as $f) {
        foreach ($f->evidence as $e) {
            $link = App\Models\ReviewTheme::where('theme_id', $f->theme_id)
                ->whereHas('analysisReview', fn ($q) => $q->where('review_id', $e->review_id))->first();
            expect($link->polarity)->toBe('negative');
        }
    }
});

it('fails honestly on empty datasets and on unusable-only datasets', function () {
    $run = runAnalysis($this->product);
    expect($run->status)->toBe('failed')->and($run->error)->toContain('no imported reviews');

    Review::factory()->for($this->product)->create(['user_id' => $this->user->id, 'body' => 'ok', 'fingerprint' => str_repeat('a', 64)]);
    $run2 = runAnalysis($this->product);
    expect($run2->status)->toBe('failed')->and($run2->error)->toContain('No usable reviews');
    expect($run2->findings()->count())->toBe(0);
});

it('flags small samples and never claims strong evidence from one review', function () {
    addReviews($this->product, [['Excellent durable quality, really sturdy.', 5]]);
    $run = runAnalysis($this->product);
    expect($run->status)->toBe('completed');
    expect($run->findings()->where('type', 'caveat')->pluck('statement')->implode(' '))->toContain('Only 1 reviews');
    expect($run->findings()->where('strength', 'strong')->count())->toBe(0);
});

it('shows contradictory evidence explicitly as a divided finding', function () {
    addReviews($this->product, [
        ['The price is great value, really affordable.', 5], ['Price is a bargain, worth every penny.', 5],
        ['Way too expensive, overpriced for this.', 1], ['The price is awful, overpriced and pricey.', 1],
    ]);
    $run = runAnalysis($this->product);
    $mixed = $run->findings()->where('type', 'mixed')->first();
    expect($mixed)->not->toBeNull()->and($mixed->statement)->toContain('divided')->and($mixed->strength)->toBe('weak');
});

it('prevents duplicate processing of the same run', function () {
    addReviews($this->product, sampleReviews());
    $run = runAnalysis($this->product);
    $before = $run->analysisReviews()->count();
    app(AnalysisPipeline::class)->run($run); // already completed: no-op
    expect($run->fresh()->analysisReviews()->count())->toBe($before);
    expect(app(AnalysisPipeline::class)->claim($run))->toBeFalse();
});

it('queues the analysis and shows pending status until the job finishes', function () {
    Queue::fake();
    addReviews($this->product, sampleReviews());
    $this->actingAs($this->user)->post("/products/{$this->product->id}/analyses")->assertRedirect();
    $run = AnalysisRun::first();
    expect($run->status)->toBe('pending');
    Queue::assertPushed(RunAnalysisJob::class);

    // a second concurrent run is refused
    $this->post("/products/{$this->product->id}/analyses")->assertSessionHas('error');
    expect(AnalysisRun::count())->toBe(1);

    $this->get("/analyses/{$run->id}")->assertInertia(fn ($p) => $p->where('analysis.status', 'pending')->missing('findings'));

    (new RunAnalysisJob($run->id))->handle(app(AnalysisPipeline::class));
    $this->get("/analyses/{$run->id}")->assertInertia(fn ($p) => $p->where('analysis.status', 'completed')->has('findings')->has('themes'));
});

it('marks a run failed when the job itself crashes', function () {
    addReviews($this->product, sampleReviews());
    $run = new AnalysisRun;
    $run->forceFill(['user_id' => $this->user->id, 'product_id' => $this->product->id, 'status' => 'running', 'engine' => 'baseline', 'pipeline_version' => '1'])->save();
    (new RunAnalysisJob($run->id))->failed(new RuntimeException('boom'));
    expect($run->fresh()->status)->toBe('failed')->and($run->fresh()->error)->not->toContain('boom');
});

it('refuses analysis with no reviews and keeps old results on reanalysis', function () {
    Queue::fake();
    $this->actingAs($this->user)->post("/products/{$this->product->id}/analyses")->assertSessionHas('error');
    addReviews($this->product, sampleReviews());
    $first = runAnalysis($this->product);
    $this->post("/analyses/{$first->id}/reanalyze")->assertRedirect();
    expect(AnalysisRun::count())->toBe(2)->and($first->fresh()->status)->toBe('completed')
        ->and(AnalysisRun::latest('id')->first()->previous_run_id)->toBe($first->id);
});

it('deletes only the analysis and not the imported reviews', function () {
    addReviews($this->product, sampleReviews());
    $run = runAnalysis($this->product);
    $this->actingAs($this->user)->delete("/analyses/{$run->id}")->assertRedirect();
    expect(AnalysisRun::count())->toBe(0)->and(Review::count())->toBe(10);
});

it('keeps analyses private between users', function () {
    addReviews($this->product, sampleReviews());
    $run = runAnalysis($this->product);
    $this->actingAs(User::factory()->create());
    $this->get("/analyses/{$run->id}")->assertNotFound();
    $this->post("/analyses/{$run->id}/reanalyze")->assertNotFound();
    $this->delete("/analyses/{$run->id}")->assertNotFound();
    $this->get("/analyses/{$run->id}/export/summary")->assertNotFound();
    $this->get("/analyses/{$run->id}/export/report")->assertNotFound();
    $review = Review::first();
    $this->get("/reviews/{$review->id}")->assertNotFound();
});

it('falls back to the baseline when the AI provider is not configured', function () {
    config(['reviewlens.ai.api_key' => null]);
    addReviews($this->product, sampleReviews());
    $run = runAnalysis($this->product, 'model');
    expect($run->status)->toBe('completed')->and($run->provenance['ai']['used'])->toBeFalse()
        ->and($run->provenance['ai']['fallback_reason'])->toContain('No AI provider')
        ->and($run->provenance['engine'])->toBe('baseline');
    expect($run->findings()->where('type', 'caveat')->pluck('statement')->implode(' '))->toContain('model-backed adapter was requested');
});

it('falls back when the AI provider errors, times out or returns junk', function (callable $fake) {
    config(['reviewlens.ai.api_key' => 'sk-test-secret']);
    Http::fake($fake());
    addReviews($this->product, sampleReviews());
    $run = runAnalysis($this->product, 'model');
    expect($run->status)->toBe('completed')->and($run->provenance['ai']['used'])->toBeFalse()
        ->and($run->analysisReviews()->where('sentiment_method', 'model')->count())->toBe(0);
    expect(json_encode($run->fresh()->toArray()))->not->toContain('sk-test-secret');
})->with([
    'http 500' => [fn () => ['api.anthropic.com/*' => Http::response('err', 500)]],
    'timeout' => [fn () => ['api.anthropic.com/*' => fn () => throw new Illuminate\Http\Client\ConnectionException('timeout')]],
    'junk' => [fn () => ['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'sorry, no json']]], 200)]],
]);

it('uses validated model output when available and keeps evidence verbatim', function () {
    config(['reviewlens.ai.api_key' => 'sk-test']);
    addReviews($this->product, sampleReviews());
    $ids = Review::orderBy('id')->pluck('id', 'body');
    $first = Review::orderBy('id')->first();
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode(['reviews' => [
        ['id' => $first->id, 'sentiment' => 'positive', 'confidence' => 'high', 'themes' => [
            ['label' => 'sound stage', 'polarity' => 'positive', 'quote' => 'Excellent quality'],
            ['label' => 'sound stage', 'polarity' => 'positive', 'quote' => 'made up text'],
        ]],
        ['id' => 987654, 'sentiment' => 'negative', 'confidence' => 'high', 'themes' => []],
    ]])]]], 200)]);

    $run = runAnalysis($this->product, 'model');
    expect($run->provenance['ai']['used'])->toBeTrue()->and($run->provenance['engine'])->toBe('model+baseline')
        ->and($run->analysisReviews()->where('sentiment_method', 'model')->count())->toBe(1);
    foreach (FindingEvidence::all() as $e) {
        expect(str_contains(Review::find($e->review_id)->body, $e->excerpt))->toBeTrue();
    }
});

it('seeds demo data that analyses fully and is clearly labelled synthetic', function () {
    app(DemoDataService::class)->seedFor($this->user);
    expect(Product::where('is_demo', true)->count())->toBe(3)
        ->and(Review::where('is_demo', false)->count())->toBe(0)
        ->and(AnalysisRun::where('status', 'completed')->count())->toBe(3);
    expect(AnalysisRun::first()->provenance['includes_demo_data'])->toBeTrue();

    app(DemoDataService::class)->seedFor($this->user); // idempotent
    expect(Product::where('is_demo', true)->count())->toBe(3);
});

it('exposes the demo button as a working route', function () {
    $this->actingAs($this->user)->post('/demo')->assertRedirect('/dashboard');
    expect(Product::where('is_demo', true)->count())->toBe(3);
});
