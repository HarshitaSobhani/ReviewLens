<?php

namespace App\Services\Nlp;

/** Per-review analysis output (in memory, before persistence). */
final class ReviewResult
{
    /**
     * @param  array<string,array{label:string,polarity:string,terms:string[],snippet:string,discovered:bool,method:string}>  $themes
     */
    public function __construct(
        public readonly int $reviewId,
        public string $body,
        public ?int $rating,
        public string $sentiment,
        public float $score,
        public string $method,
        public string $confidence,
        public bool $conflict,
        public array $themes = [],
    ) {}
}
