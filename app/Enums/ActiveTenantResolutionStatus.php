<?php

declare(strict_types=1);

namespace App\Enums;

enum ActiveTenantResolutionStatus: string
{
    case Resolved = 'resolved';
    case SelectionRequired = 'selection_required';
    case NoAccessibleTenant = 'no_accessible_tenant';
}
