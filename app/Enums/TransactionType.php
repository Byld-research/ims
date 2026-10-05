<?php

namespace App\Enums;

enum TransactionType: string
{
    case Receipt = 'RECEIPT';
    case IssueMachine = 'ISSUE_MACHINE';
    case IssueGeneral = 'ISSUE_GENERAL';
    case TransferOut = 'TRANSFER_OUT';
    case TransferIn = 'TRANSFER_IN';
    case Adjustment = 'ADJUSTMENT';

    public function label(): string
    {
        return match ($this) {
            self::Receipt => 'Receipt',
            self::IssueMachine => 'Issue to machine',
            self::IssueGeneral => 'General issue',
            self::TransferOut => 'Transfer out',
            self::TransferIn => 'Transfer in',
            self::Adjustment => 'Adjustment',
        };
    }

    /**
     * Fixed direction of the movement; null when either direction is allowed.
     */
    public function isIncoming(): ?bool
    {
        return match ($this) {
            self::Receipt, self::TransferIn => true,
            self::IssueMachine, self::IssueGeneral, self::TransferOut => false,
            self::Adjustment => null,
        };
    }
}
