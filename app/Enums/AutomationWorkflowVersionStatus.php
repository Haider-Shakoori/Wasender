<?php

namespace App\Enums;

enum AutomationWorkflowVersionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Superseded = 'superseded';

    public function immutable(): bool
    {
        return $this !== self::Draft;
    }
}
