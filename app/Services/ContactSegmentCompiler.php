<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class ContactSegmentCompiler
{
    private const FIELDS = ['status', 'consent_status', 'source', 'company', 'preferred_language', 'opted_out_at', 'suppressed_at', 'blocked_at'];

    private const OPS = ['eq', 'neq', 'contains', 'is_null', 'not_null'];

    public function apply(Builder $query, array $definition): Builder
    {
        $rules = $definition['rules'] ?? [];
        if (count($rules) > config('contacts.segment_max_rules')) {
            throw ValidationException::withMessages(['definition' => 'Too many segment rules.']);
        }foreach ($rules as $rule) {
            $field = $rule['field'] ?? '';
            $op = $rule['operator'] ?? '';
            $value = $rule['value'] ?? null;
            if (! in_array($field, self::FIELDS, true) || ! in_array($op, self::OPS, true)) {
                throw ValidationException::withMessages(['definition' => 'Segment contains an unapproved field or operator.']);
            }match ($op) {
                'eq' => $query->where($field, $value),'neq' => $query->where($field, '!=', $value),'contains' => $query->where($field, 'like', '%'.addcslashes((string) $value, '%_').'%'),'is_null' => $query->whereNull($field),'not_null' => $query->whereNotNull($field)
            };
        }

        return $query;
    }
}
