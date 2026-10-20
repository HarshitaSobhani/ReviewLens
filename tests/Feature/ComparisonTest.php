<?php

use App\Models\AnalysisRun;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\Analysis\AnalysisPipeline;
use App\Services\Demo\DemoDataService;

beforeEach(fn () => $this->user = User::factory()->create());

function analyzed(User $u, string $name, array $reviews, ?string $source = null): AnalysisRun
{
    $p = Product::factory()->for($u)->create(['name' => $name]);
    foreach ($reviews as [$b, $r]) {
        Review::factory()->for($p)->withBody($b)->create(['user_id' => $u->id, 'rating' => $r, 'source' => $source]);
    }
    $run = new AnalysisRun;
    $run->forceFill(['user_id' => $u->id, 'product_id' => $p->id, 'status' => 'pending', 'engine' => 'baseline', 'pipeline_version' => '1'])->save();
    app(AnalysisPipeline::class)->run($run);

    return $run->fresh();
}

it('compares two analyses, warns about volume and sources, and never names a winner', function () {
    $a = analyzed($this->user, 'A', [
        ['Great battery, lasts for days and charges fast.', 5], ['Battery is excellent and reliable.', 5],
        ['Battery life is wonderful, lasts and lasts.', 5], ['The strap is poor and flimsy, it broke.', 2],
        ['Quality is poor, cracked and flimsy.', 1], ['Quality is terrible, broke and useless.', 1],
    ], 'Shop X');
    $b = analyzed($this->user, 'B', [['Battery is awful, drains fast and died.', 1], ['Terrible battery, it drains quickly.', 1]], 'Shop Y');
    // make B tiny vs A to trigger the volume warning (6 vs 2 => 3x)

    $res = $this->actingAs($this->user)->get("/compare?analyses[]={$a->id}&analyses[]={$b->id}");
    $res->assertOk()->assertInertia(function ($p) {
        $p->has('comparison.items', 2)
            ->where('comparison.verdict', fn ($v) => str_contains($v, 'does not declare'))
            ->where('comparison.warnings', fn ($w) => collect($w)->contains(fn ($x) => str_contains($x, 'volumes differ'))
                && collect($w)->contains(fn ($x) => str_contains($x, 'different or unspecified sources'))
                && collect($w)->contains(fn ($x) => str_contains($x, 'fewer than 20')))
            ->has('comparison.themes')
            ->missing('comparison.winner');
    });
});

it('requires at least two of the users own completed analyses', function () {
    $a = analyzed($this->user, 'A', [['Great quality product, durable.', 5]]);
    $other = analyzed(User::factory()->create(), 'Theirs', [['Great quality product, durable.', 5]]);
    $this->actingAs($this->user)->get("/compare?analyses[]={$a->id}")->assertInertia(fn ($p) => $p->where('comparison', null)->where('error', fn ($e) => str_contains($e, 'at least two')));
    $this->get("/compare?analyses[]={$a->id}&analyses[]={$other->id}")->assertInertia(fn ($p) => $p->where('comparison', null)->where('error', fn ($e) => $e !== null));
});

it('compares seeded demo products and flags synthetic data', function () {
    app(DemoDataService::class)->seedFor($this->user);
    $ids = AnalysisRun::pluck('id')->take(2)->all();
    $this->actingAs($this->user)->get('/compare?'.http_build_query(['analyses' => $ids]))
        ->assertInertia(fn ($p) => $p->where('comparison.warnings', fn ($w) => collect($w)->contains(fn ($x) => str_contains($x, 'synthetic'))));
});
