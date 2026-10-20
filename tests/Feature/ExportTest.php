<?php

use App\Models\AnalysisRun;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\Analysis\AnalysisPipeline;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->product = Product::factory()->for($this->user)->create();
    foreach ([
        ['=HYPERLINK("http://evil.example","click") great quality and durable', 5],
        ['Excellent quality, very durable and sturdy construction.', 5],
        ['Poor quality, it broke quickly, flimsy.', 1],
    ] as [$b, $r]) {
        Review::factory()->for($this->product)->withBody($b)->create(['user_id' => $this->user->id, 'rating' => $r, 'source' => '@SUM(1)']);
    }
    $run = new AnalysisRun;
    $run->forceFill(['user_id' => $this->user->id, 'product_id' => $this->product->id, 'status' => 'pending', 'engine' => 'baseline', 'pipeline_version' => '1'])->save();
    app(AnalysisPipeline::class)->run($run);
    $this->run = $run->fresh();
});

it('exports review rows with real stored data and neutralises formulas', function () {
    $res = $this->actingAs($this->user)->get("/analyses/{$this->run->id}/export/reviews");
    $res->assertOk()->assertDownload();
    $csv = $res->streamedContent();
    expect($csv)->toContain('Excellent quality, very durable')
        ->and($csv)->toContain("'=HYPERLINK")
        ->and($csv)->toContain("'@SUM(1)");
    foreach (preg_split('/\R/', $csv) as $line) {
        expect($line)->not->toMatch('/(^|,)"?=HYPERLINK/');
    }
    expect(substr_count(trim($csv), "\n"))->toBeGreaterThanOrEqual(3);
});

it('exports summary and themes csv with evidence ids', function () {
    $s = $this->actingAs($this->user)->get("/analyses/{$this->run->id}/export/summary")->streamedContent();
    expect($s)->toContain('finding_type')->and($s)->toContain('overview');
    $t = $this->get("/analyses/{$this->run->id}/export/themes")->streamedContent();
    expect($t)->toContain('Quality & durability');
});

it('rejects unknown export types and unfinished analyses', function () {
    $this->actingAs($this->user)->get("/analyses/{$this->run->id}/export/secrets")->assertNotFound();
    $this->run->forceFill(['status' => 'running'])->save();
    $this->get("/analyses/{$this->run->id}/export/summary")->assertStatus(409);
});

it('downloads an escaped html report', function () {
    Review::first()->forceFill(['title' => '<script>x</script>'])->save();
    $res = $this->actingAs($this->user)->get("/analyses/{$this->run->id}/export/report");
    $res->assertOk();
    expect($res->headers->get('Content-Disposition'))->toContain('attachment');
    expect($res->getContent())->toContain($this->product->name)->not->toContain('<script>x');
});

it('blocks other users from exporting', function () {
    $this->actingAs(User::factory()->create())->get("/analyses/{$this->run->id}/export/reviews")->assertNotFound();
});
