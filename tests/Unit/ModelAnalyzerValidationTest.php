<?php

use App\Services\Nlp\AnthropicModelAnalyzer;
use App\Services\Nlp\ModelAnalysisException;

it('keeps only valid labels for known review ids and rejects fabricated quotes', function () {
    $reviews = [10 => 'The battery is great but the strap broke', 11 => 'Fine'];
    $raw = json_encode(['reviews' => [
        ['id' => 10, 'sentiment' => 'mixed', 'confidence' => 'high', 'themes' => [
            ['label' => 'Battery', 'polarity' => 'positive', 'quote' => 'The battery is great'],
            ['label' => 'Strap', 'polarity' => 'negative', 'quote' => 'invented quote'],
            ['label' => 'Bad', 'polarity' => 'sideways', 'quote' => null],
        ]],
        ['id' => 999, 'sentiment' => 'positive', 'confidence' => 'high', 'themes' => []],
        ['id' => 11, 'sentiment' => 'banana', 'confidence' => 'high', 'themes' => []],
    ]]);
    $out = (new AnthropicModelAnalyzer)->validate("Here you go:\n".$raw, $reviews);

    expect(array_keys($out))->toBe([10])
        ->and($out[10]['themes'])->toHaveCount(2)
        ->and($out[10]['themes'][0]['quote'])->toBe('The battery is great')
        ->and($out[10]['themes'][1]['quote'])->toBeNull();
});

it('throws on non-json output', function () {
    (new AnthropicModelAnalyzer)->validate('I cannot help', [1 => 'text']);
})->throws(ModelAnalysisException::class);
