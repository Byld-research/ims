<?php

namespace App\Exceptions;

use DomainException;

/**
 * A stock count action the rules refuse (SPEC 5.6). The message is written for the user.
 */
class StockCountException extends DomainException
{
    public function __construct(string $message, public readonly string $field = 'count')
    {
        parent::__construct($message);
    }
}
