<?php

use App\Services\Export\CsvSanitizer;

it('neutralises formula injection', function (string $in) {
    expect(CsvSanitizer::cell($in))->toStartWith("'");
})->with(['=SUM(A1)', '+1+1', '-2+3', '@cmd', "\t=1", ' =1+1', "=HYPERLINK(\"http://x\")", '%x', '|calc']);

it('leaves normal values and numbers untouched', function () {
    expect(CsvSanitizer::cell('Great product'))->toBe('Great product')
        ->and(CsvSanitizer::cell(5))->toBe(5)
        ->and(CsvSanitizer::cell(null))->toBe('')
        ->and(CsvSanitizer::cell(true))->toBe('yes')
        ->and(CsvSanitizer::cell('-5'))->toBe('-5');
});
