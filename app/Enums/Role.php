<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'ADMIN';
    case Manager = 'MANAGER';
    case Operator = 'OPERATOR';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Manager => 'Manager',
            self::Operator => 'Operator',
        };
    }
}
