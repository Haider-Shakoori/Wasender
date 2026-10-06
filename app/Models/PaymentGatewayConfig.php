<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

final class PaymentGatewayConfig extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'provider',
        'is_enabled',
        'is_default',
        'mode',
        'configuration',
        'credentials_encrypted',
        'last_webhook_at',
        'last_webhook_status',
    ];

    protected $hidden = ['credentials_encrypted'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'is_default' => 'boolean',
            'configuration' => 'array',
            'credentials_encrypted' => 'encrypted:array',
            'last_webhook_at' => 'datetime',
        ];
    }
}
