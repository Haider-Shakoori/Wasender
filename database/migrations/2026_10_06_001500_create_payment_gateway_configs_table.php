<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_configs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('provider', 40)->unique();
            $table->boolean('is_enabled')->default(false)->index();
            $table->boolean('is_default')->default(false)->index();
            $table->string('mode', 16)->default('test');
            $table->json('configuration')->nullable();
            $table->text('credentials_encrypted')->nullable();
            $table->timestamp('last_webhook_at')->nullable();
            $table->string('last_webhook_status', 40)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_configs');
    }
};
