<?php

namespace Database\Factories;

use App\Models\Product;
use App\Services\Nlp\TextNormalizer;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewFactory extends Factory
{
    public function definition(): array
    {
        $body = fake()->unique()->sentence(12);

        return [
            'user_id' => fn (array $a) => Product::find($a['product_id'])->user_id,
            'product_id' => Product::factory(),
            'body' => $body,
            'fingerprint' => (new TextNormalizer)->fingerprint(null, $body),
            'rating' => fake()->numberBetween(1, 5),
            'reviewed_at' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'is_demo' => false,
        ];
    }

    public function withBody(string $body): static
    {
        return $this->state(fn () => ['body' => $body, 'fingerprint' => (new TextNormalizer)->fingerprint(null, $body)]);
    }
}
