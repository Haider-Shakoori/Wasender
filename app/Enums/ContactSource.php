<?php

namespace App\Enums;

enum ContactSource: string
{
    case Manual = 'manual';
    case Import = 'import';
    case TransactionalMessage = 'transactional_message';
    case System = 'system';
}
