<?php

namespace App\Services\Nlp;

interface ModelAnalyzer
{
    public function isConfigured(): bool;

    public function name(): string;

    /**
     * @param  array<int,string>  $reviews  reviewId => text
     * @return array<int,array{sentiment:string,confidence:string,themes:array<int,array{label:string,polarity:string,quote:?string}>}>
     *
     * @throws ModelAnalysisException on timeout, HTTP failure or invalid structured output
     */
    public function analyze(array $reviews): array;
}
