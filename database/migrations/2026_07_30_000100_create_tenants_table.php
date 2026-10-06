<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->string('name');
            $table->string('slug');
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->char('country', 2)->nullable();
            $table->string('timezone', 64)->default((string) config('saas.default_timezone'));
            $table->char('currency', 3)->default((string) config('saas.default_currency'));
            $table->string('locale', 10)->default((string) config('saas.default_locale'));
            $table->string('logo_path')->nullable();
            $table->string('status', 20)->default((string) config('saas.default_tenant_status'));
            $table->boolean('is_active')->default(true);
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('uuid', 'tenants_uuid_uq');
            $table->unique('slug', 'tenants_slug_uq');
            $table->index('status', 'tenants_status_idx');
            $table->index('owner_id', 'tenants_owner_idx');
            $table->index(['is_active', 'status'], 'tenants_active_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
