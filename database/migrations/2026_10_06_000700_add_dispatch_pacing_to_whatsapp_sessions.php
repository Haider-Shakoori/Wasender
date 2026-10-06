<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table): void {
            $table->timestamp('next_send_at', 3)->nullable()->index()->after('last_reconnect_attempt_at');
            $table->timestamp('last_dispatch_reserved_at', 3)->nullable()->after('next_send_at');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table): void {
            $table->dropIndex(['next_send_at']);
            $table->dropColumn(['next_send_at', 'last_dispatch_reserved_at']);
        });
    }
};
