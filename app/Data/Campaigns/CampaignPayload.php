<?php

namespace App\Data\Campaigns;

final readonly class CampaignPayload
{
    public function __construct(public array $values) {}

    public function canonical(): array
    {
        return self::sort($this->values);
    }

    private static function sort(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        } if (array_is_list($value)) {
            $v = array_map(self::sort(...), $value);
            sort($v);

            return $v;
        } ksort($value);
        foreach ($value as $k => $v) {
            $value[$k] = self::sort($v);
        }

        return $value;
    }
}
