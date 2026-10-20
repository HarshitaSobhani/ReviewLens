<?php

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\Demo\DemoDataService;

it('searches, filters, sorts and paginates reviews', function () {
    $user = User::factory()->create();
    app(DemoDataService::class)->seedFor($user);
    $p = Product::where('name', 'like', '%Nimbus%')->first();
    $this->actingAs($user);

    $this->get("/products/{$p->id}/reviews")->assertInertia(fn ($page) => $page->where('reviews.total', 36)->has('reviews.data', 15)->has('themes'));
    $this->get("/products/{$p->id}/reviews?rating=5")->assertInertia(fn ($page) => $page
        ->where('reviews.data', fn ($d) => collect($d)->every(fn ($r) => $r['rating'] === 5)));
    $this->get("/products/{$p->id}/reviews?sentiment=negative")->assertInertia(fn ($page) => $page
        ->where('reviews.data', fn ($d) => count($d) > 0 && collect($d)->every(fn ($r) => $r['sentiment'] === 'negative')));
    $this->get("/products/{$p->id}/reviews?theme=quality_durability")->assertInertia(fn ($page) => $page->where('reviews.total', fn ($t) => $t > 0 && $t < 36));
    $this->get("/products/{$p->id}/reviews?sort=rating_asc")->assertInertia(fn ($page) => $page->where('reviews.data.0.rating', 1));
    $this->get("/products/{$p->id}/reviews?q=delivery")->assertInertia(fn ($page) => $page
        ->where('reviews.data', fn ($d) => collect($d)->every(fn ($r) => stripos($r['body'], 'delivery') !== false)));
    $this->get("/products/{$p->id}/reviews?page=3")->assertInertia(fn ($page) => $page->has('reviews.data', 6));
    $this->get("/products/{$p->id}/reviews?sort=drop_table")->assertSessionHasErrors('sort');
});

it('labels demo reviews and shows the review detail with analysis labels', function () {
    $user = User::factory()->create();
    app(DemoDataService::class)->seedFor($user);
    $review = Review::first();
    $this->actingAs($user)->get("/reviews/{$review->id}")->assertInertia(fn ($p) => $p
        ->where('review.is_demo', true)->where('review.source', 'Synthetic demo data')->has('labels.sentiment'));
});
