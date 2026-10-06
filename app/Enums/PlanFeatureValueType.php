<?php

namespace App\Enums;

enum PlanFeatureValueType: string
{
    case Boolean = 'boolean';
    case Integer = 'integer';
    case String = 'string';
}
