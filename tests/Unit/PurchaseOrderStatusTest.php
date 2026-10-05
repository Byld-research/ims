<?php

use App\Enums\PurchaseOrderStatus as S;

test('transitions follow the state diagram', function (S $from, S $to, bool $allowed) {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with([
    [S::Draft, S::Ordered, true],
    [S::Draft, S::Received, false],
    [S::Ordered, S::Received, true],
    [S::Confirmed, S::PartiallyReceived, true],
    [S::Confirmed, S::Received, true],
    [S::Confirmed, S::Cancelled, true],
    [S::Shipped, S::Cancelled, false],
    [S::PartiallyReceived, S::Cancelled, false],
    [S::Received, S::Closed, true],
    [S::Closed, S::Draft, false],
]);

test('receipts are accepted only while the order is open', function () {
    $accepting = array_filter(S::cases(), fn (S $s) => $s->acceptsReceipts());

    expect(array_values($accepting))->toBe([S::Ordered, S::Confirmed, S::Shipped, S::PartiallyReceived]);
});
