<?php

use App\Services\Nlp\TextNormalizer;

beforeEach(fn () => $this->n = new TextNormalizer);

it('strips html, control characters and collapses whitespace', function () {
    expect($this->n->clean("  <b>Great</b>\x00   product\n\n really &amp; truly  "))->toBe('Great product really & truly');
});

it('fingerprints ignoring case, punctuation and spacing', function () {
    expect($this->n->fingerprint(null, 'Great  product!!'))->toBe($this->n->fingerprint(null, 'great product'))
        ->and($this->n->fingerprint(null, 'great product'))->not->toBe($this->n->fingerprint(null, 'great products'));
});

it('flags unusable text', function () {
    expect($this->n->unusableReason(''))->not->toBeNull()
        ->and($this->n->unusableReason('ok'))->not->toBeNull()
        ->and($this->n->unusableReason('!!!!!!!!!!!!'))->not->toBeNull()
        ->and($this->n->unusableReason('This works well enough'))->toBeNull();
});

it('normalizes ratings to 1-5 stars', function (mixed $in, ?int $out) {
    expect($this->n->normalizeRating($in))->toBe($out);
})->with([
    [5, 5], ['4', 4], ['4.5', 5], ['3/5', 3], ['8/10', 4], ['100/100', 5], ['', null], [null, null],
    ['abc', null], [7, null], ['0', null], ['4,2', 4],
]);

it('splits sentences', function () {
    expect($this->n->sentences('One thing. Another thing! Third?'))->toHaveCount(3);
});
