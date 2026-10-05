<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'DRAFT';
    case Ordered = 'ORDERED';
    case Confirmed = 'CONFIRMED';
    case Shipped = 'SHIPPED';
    case PartiallyReceived = 'PARTIALLY_RECEIVED';
    case Received = 'RECEIVED';
    case Closed = 'CLOSED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return ucfirst(strtolower(str_replace('_', ' ', $this->value)));
    }

    /**
     * Allowed transitions, mirroring the state diagram in SPEC 5.3.
     *
     * @return list<self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Draft => [self::Ordered, self::Cancelled],
            self::Ordered => [self::Confirmed, self::PartiallyReceived, self::Received, self::Cancelled],
            self::Confirmed => [self::Shipped, self::PartiallyReceived, self::Received, self::Cancelled],
            self::Shipped => [self::PartiallyReceived, self::Received],
            self::PartiallyReceived => [self::Received],
            self::Received => [self::Closed],
            self::Closed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->transitions(), true);
    }

    public function acceptsReceipts(): bool
    {
        return in_array($this, [self::Ordered, self::Confirmed, self::Shipped, self::PartiallyReceived], true);
    }

    public function isOpen(): bool
    {
        return $this->acceptsReceipts();
    }
}
