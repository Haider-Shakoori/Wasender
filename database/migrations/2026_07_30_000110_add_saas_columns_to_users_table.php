<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_platform_admin')->default(false)->after('password');
            $table->unsignedBigInteger('last_active_tenant_id')->nullable()->after('id');
            $table->index('last_active_tenant_id', 'users_last_tenant_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_last_tenant_idx');
            $table->dropColumn(['last_active_tenant_id', 'is_platform_admin']);
        });
    }
};
