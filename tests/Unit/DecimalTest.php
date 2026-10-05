<?php

use App\Support\Decimal;

test('rounds half up to four places instead of truncating', function (string $value, string $expected) {
    expect(Decimal::round($value, 4))->toBe($expected);
})->with([
    ['4.666666666666', '4.6667'],
    ['4.66664', '4.6666'],
    ['4.66665', '4.6667'],
    ['6', '6.0000'],
    ['-30.00005', '-30.0001'],
    ['-0.00001', '0.0000'],
]);

test('the spec example average comes out as 4.6667', function () {
    // SPEC 13.4: 10 units at 4.0000 plus 5 transferred in at 6.0000.
    $total = Decimal::add(Decimal::mul('10', '4.0000'), Decimal::mul('5', '6.0000'));

    expect(Decimal::round(Decimal::div($total, '15'), 4))->toBe('4.6667');
});

test('refuses floats in scientific notation and non-numbers', function (string $value) {
    Decimal::round($value, 2);
})->with(['1e5', 'abc', ''])->throws(InvalidArgumentException::class);

test('refuses division by zero', function () {
    Decimal::div('1', '0.000');
})->throws(InvalidArgumentException::class);
