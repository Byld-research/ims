<?php

use App\Support\Format;

test('quantities drop trailing zeros', function (string $value, string $expected) {
    expect(Format::qty($value))->toBe($expected);
})->with([
    ['10.000', '10'],
    ['2.500', '2.5'],
    ['1234.125', '1,234.125'],
]);

test('money shows two places with thousands separators', function (string $value, string $expected) {
    expect(Format::money($value))->toBe($expected);
})->with([
    ['82000', '82,000.00'],
    ['4.6667', '4.67'],
    ['-30.0050', '-30.01'],
    ['1234567.8949', '1,234,567.89'],
]);
