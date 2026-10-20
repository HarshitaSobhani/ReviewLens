<?php

use App\Services\Nlp\LexiconSentimentClassifier;
use App\Services\Nlp\TextNormalizer;

beforeEach(fn () => $this->c = new LexiconSentimentClassifier(new TextNormalizer));

it('classifies clear positive text', function () {
    $r = $this->c->classify('Excellent quality, I love it and it works great', 5);
    expect($r['sentiment'])->toBe('positive')->and($r['confidence'])->toBe('high')->and($r['conflict'])->toBeFalse();
});

it('classifies clear negative text', function () {
    $r = $this->c->classify('Terrible, it broke and I want a refund', 1);
    expect($r['sentiment'])->toBe('negative');
});

it('handles negation', function () {
    expect($this->c->classify('This is not good at all', null)['sentiment'])->toBe('negative')
        ->and($this->c->classify("It doesn't break and isn't flimsy, not bad", null)['sentiment'])->not->toBe('negative');
});

it('detects mixed reviews', function () {
    $r = $this->c->classify('Great sound but terrible battery', 3);
    expect($r['sentiment'])->toBe('mixed');
});

it('flags rating and text conflicts with low confidence', function () {
    $r = $this->c->classify('Awful, broken, total waste', 5);
    expect($r['sentiment'])->toBe('negative')->and($r['conflict'])->toBeTrue()->and($r['confidence'])->toBe('low');
});

it('falls back to rating when text has no signal and says so', function () {
    $r = $this->c->classify('Arrived on a Tuesday in a brown carton', 5);
    expect($r['sentiment'])->toBe('positive')->and($r['method'])->toBe('rating');
    $none = $this->c->classify('Arrived on a Tuesday in a brown carton', null);
    expect($none['method'])->toBe('none')->and($none['confidence'])->toBe('low');
});
