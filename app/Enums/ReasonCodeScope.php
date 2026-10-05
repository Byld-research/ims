<?php

namespace App\Enums;

enum ReasonCodeScope: string
{
    case Adjustment = 'ADJUSTMENT';
    case IssueGeneral = 'ISSUE_GENERAL';
}
