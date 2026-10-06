<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('tenant_subscriptions')->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->string('payment_method', 30)->default('manual');
            $table->string('reference', 120)->nullable();
            $table->string('status', 20)->default('paid');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 1000)->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_payments');
    }
};
