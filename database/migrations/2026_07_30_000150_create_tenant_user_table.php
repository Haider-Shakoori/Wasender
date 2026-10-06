<?php

declare(strict_types=1);

use App\Enums\MembershipStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default(MembershipStatus::Invited->value);
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id'], 'tu_tenant_user_uq');
            $table->index(['tenant_id', 'status'], 'tu_tenant_status_idx');
            $table->index(['user_id', 'status'], 'tu_user_status_idx');
            $table->index('role_id', 'tu_role_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_user');
    }
};
