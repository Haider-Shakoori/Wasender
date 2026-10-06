<?php

namespace App\Support;

final class SensitiveDataMasker
{
    public static function ip(?string $ip): string
    {
        if (! $ip) {
            return 'Unknown';
        }if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);

            return $parts[0].'.'.$parts[1].'.***.***';
        }

        return substr($ip, 0, 12).'…';
    }

    public static function token(?string $value): string
    {
        return $value ? '••••••••'.substr($value, -4) : 'Not set';
    }

    public static function session(string $id): string
    {
        return substr(hash('sha256', $id), 0, 12);
    }
}
