<?php

namespace App\Events;

use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class WhatsAppMessageStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(public WhatsAppMessage $message) {}
}
