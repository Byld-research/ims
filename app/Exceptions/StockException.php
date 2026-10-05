<?php

namespace App\Exceptions;

use App\Support\Format;
use DomainException;

/**
 * A stock movement the business rules refuse (SPEC 5). The message is written for the user.
 */
class StockException extends DomainException
{
    public static function insufficient(string $available, string $uom, string $siteCode): self
    {
        return new self(__('Only :qty :uom available at :site.', [
            'qty' => Format::qty($available), 'uom' => $uom, 'site' => $siteCode,
        ]));
    }

    public static function costRequired(string $siteCode): self
    {
        return new self(__('Enter a unit cost: this item has no average cost yet at :site.', ['site' => $siteCode]));
    }
}
