<?php

use App\Models\Product;
use App\Models\Review;
use App\Models\User;

beforeEach(fn () => $this->user = User::factory()->create());

it('creates, updates and validates products', function () {
    $this->actingAs($this->user)->post('/products', ['name' => ''])->assertSessionHasErrors('name');
    $this->actingAs($this->user)->post('/products', ['name' => 'Kettle', 'price' => -3, 'image_url' => 'javascript:alert(1)'])
        ->assertSessionHasErrors(['price', 'image_url']);

    $this->post('/products', ['name' => 'Kettle', 'category' => 'Home', 'price' => '29.90', 'image_url' => 'https://example.com/a.png'])
        ->assertRedirect();
    $p = Product::first();
    $this->put("/products/{$p->id}", ['name' => 'Kettle Pro'])->assertRedirect();
    expect($p->fresh()->name)->toBe('Kettle Pro');
});

it('allows duplicate product names (name is not an identifier)', function () {
    Product::factory()->for($this->user)->create(['name' => 'Same']);
    $this->actingAs($this->user)->post('/products', ['name' => 'Same'])->assertRedirect();
    expect(Product::where('name', 'Same')->count())->toBe(2);
});

it('searches and filters products and escapes LIKE wildcards', function () {
    Product::factory()->for($this->user)->create(['name' => 'Alpha 100%', 'category' => 'A']);
    Product::factory()->for($this->user)->create(['name' => 'Alpha 1000', 'category' => 'B']);
    $this->actingAs($this->user)->get('/products?q=100%25')
        ->assertInertia(fn ($page) => $page->has('products.data', 1)->where('products.data.0.name', 'Alpha 100%'));
    $this->get('/products?category=B')->assertInertia(fn ($page) => $page->has('products.data', 1));
});

it('hides other users products (IDOR)', function () {
    $other = Product::factory()->for(User::factory())->create();
    $this->actingAs($this->user);
    $this->get("/products/{$other->id}")->assertNotFound();
    $this->get("/products/{$other->id}/edit")->assertNotFound();
    $this->put("/products/{$other->id}", ['name' => 'Hacked'])->assertNotFound();
    $this->delete("/products/{$other->id}")->assertNotFound();
    $this->get("/products/{$other->id}/reviews")->assertNotFound();
    $this->post("/products/{$other->id}/analyses")->assertNotFound();
    $this->get("/products/{$other->id}/imports/create")->assertNotFound();
    expect($other->fresh()->name)->not->toBe('Hacked');
});

it('cascades deletion to reviews', function () {
    $p = Product::factory()->for($this->user)->create();
    Review::factory()->for($p)->count(2)->create(['user_id' => $this->user->id]);
    $this->actingAs($this->user)->delete("/products/{$p->id}")->assertRedirect('/products');
    expect(Review::count())->toBe(0);
});
