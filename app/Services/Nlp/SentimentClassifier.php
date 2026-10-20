<?php

namespace App\Services\Nlp;

interface SentimentClassifier
{
    /** @return array{sentiment:string,score:float,method:string,confidence:string,conflict:bool} */
    public function classify(string $text, ?int $rating): array;
}
