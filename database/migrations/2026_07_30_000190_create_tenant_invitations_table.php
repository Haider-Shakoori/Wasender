<?php

declare(strict_types=1);

use App\Enums\TenantInvitationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_invitations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('ti_uuid_uq');
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->char('token_hash', 64);
            $table->string('status', 20)->default(TenantInvitationStatus::Pending->value);
            $table->timestamp('expires_at');
            $table->foreignId('invited_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->unsignedInteger('send_count')->default(1);
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'email'], 'ti_tenant_email_idx');
            $table->index(['tenant_id', 'status'], 'ti_tenant_status_idx');
            $table->index('expires_at', 'ti_expires_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_invitations');
    }
};
