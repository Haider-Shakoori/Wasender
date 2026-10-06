<?php

namespace App\Enums;

enum ContactStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Invalid = 'invalid';
    case Archived = 'archived';
}
