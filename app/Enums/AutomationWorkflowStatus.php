<?php

namespace App\Enums;

enum AutomationWorkflowStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Disabled = 'disabled';
    case Archived = 'archived';
}
