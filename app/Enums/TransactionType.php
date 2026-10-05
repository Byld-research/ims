<?php

namespace App\Enums;

enum TransactionType: string
{
    case Receipt = 'RECEIPT';
    case IssueWorkCenter = 'ISSUE_WORK_CENTER';
    case IssueGeneral = 'ISSUE_GENERAL';
    case TransferOut = 'TRANSFER_OUT';
    case TransferIn = 'TRANSFER_IN';
    case Adjustment = 'ADJUSTMENT';

    public function label(): string
    {
        return match ($this) {
            self::Receipt => 'Receipt',
            self::IssueWorkCenter => 'Issue to work centre',
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
            self::IssueWorkCenter, self::IssueGeneral, self::TransferOut => false,
            self::Adjustment => null,
        };
    }
}
