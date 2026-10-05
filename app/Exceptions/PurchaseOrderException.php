<?php

namespace App\Exceptions;

use DomainException;

/**
 * A purchase order action the lifecycle rules refuse (SPEC 5.3). The message is written for the user.
 */
class PurchaseOrderException extends DomainException
{
    public function __construct(string $message, public readonly string $field = 'order')
    {
        parent::__construct($message);
    }
}
