<?php

use App\Services\Nlp\AspectExtractor;
use App\Services\Nlp\LexiconSentimentClassifier;
use App\Services\Nlp\TextNormalizer;

beforeEach(function () {
    $n = new TextNormalizer;
    $this->x = new AspectExtractor($n, new LexiconSentimentClassifier($n));
});

it('assigns aspects with clause-level polarity', function () {
    $t = $this->x->extract('The battery life is fantastic but the price is too expensive.');
    expect($t)->toHaveKeys(['performance', 'price_value'])
        ->and($t['performance']['polarity'])->toBe('positive')
        ->and($t['price_value']['polarity'])->toBe('negative')
        ->and($t['performance']['terms'])->toContain('battery');
});

it('returns snippets that are substrings of the review', function () {
    $text = 'Delivery was late. The box was damaged. Otherwise fine.';
    foreach ($this->x->extract($text) as $theme) {
        expect(str_contains($text, $theme['snippet']))->toBeTrue();
    }
});

it('does not assign unrelated aspects', function () {
    expect($this->x->extract('Completely unrelated gibberish words appear here'))->toBe([]);
});

it('discovers frequent product-specific terms but not stopwords or openers', function () {
    $texts = [];
    for ($i = 1; $i <= 6; $i++) {
        $texts[$i] = "Honestly the zipper pocket is fine number {$i}. Overall nothing else to say.";
    }
    for ($i = 7; $i <= 12; $i++) {
        $texts[$i] = "Overall this arrived as described and I kept it, number {$i}.";
    }
    $found = $this->x->discover($texts);
    expect($found)->toHaveKey('discovered_zipper')->and($found)->not->toHaveKey('discovered_overall');
    expect($found['discovered_zipper'])->toHaveCount(6)->and($found['discovered_zipper'][1]['discovered'])->toBeTrue();
});
