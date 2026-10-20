<?php

use App\Models\User;

it('shows the public landing page', function () {
    $this->get('/')->assertOk();
});

it('redirects guests away from protected pages', function (string $url) {
    $this->get($url)->assertRedirect('/login');
})->with(['/dashboard', '/products', '/analyses', '/compare']);

it('registers, logs in and logs out', function () {
    $this->post('/register', ['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'longpassword1', 'password_confirmation' => 'longpassword1'])
        ->assertRedirect('/dashboard');
    $this->assertAuthenticated();
    $this->post('/logout')->assertRedirect('/');
    $this->assertGuest();
    $this->post('/login', ['email' => 'ada@example.com', 'password' => 'longpassword1'])->assertRedirect('/dashboard');
});

it('rejects bad credentials and weak passwords', function () {
    User::factory()->create(['email' => 'a@example.com']);
    $this->post('/login', ['email' => 'a@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
    $this->post('/register', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'short', 'password_confirmation' => 'short'])
        ->assertSessionHasErrors('password');
});

it('does not allow mass-assigning the owner when creating a product', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($user)->post('/products', ['name' => 'Kettle', 'user_id' => $other->id, 'is_demo' => true])->assertRedirect();
    $p = App\Models\Product::first();
    expect($p->user_id)->toBe($user->id)->and($p->is_demo)->toBeFalse();
});
