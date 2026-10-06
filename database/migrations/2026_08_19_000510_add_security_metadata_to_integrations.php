<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table): void {
            $table->timestamp('last_used_at')->nullable()->after('credentials_encrypted');
            $table->index(['tenant_id', 'last_used_at'], 'integration_last_used_idx');
        });
    }

    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table): void {
            $table->dropIndex('integration_last_used_idx');
            $table->dropColumn('last_used_at');
        });
    }
};
