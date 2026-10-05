<?php

namespace App\Enums;

enum AuditAction: string
{
    case Create = 'CREATE';
    case Update = 'UPDATE';
    case Delete = 'DELETE';
}
