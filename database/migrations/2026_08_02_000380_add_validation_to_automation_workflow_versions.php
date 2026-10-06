<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_workflow_versions', function (Blueprint $table): void {
            $table->char('definition_hash', 64)->nullable()->after('settings');
            $table->string('validation_status', 20)->nullable()->after('definition_hash');
            $table->json('validation_summary')->nullable()->after('validation_status');
            $table->unsignedSmallInteger('schema_version')->default(1)->after('validation_summary');
            $table->timestamp('validated_at')->nullable()->after('schema_version');
            $table->index(['automation_workflow_id', 'definition_hash'], 'auto_wfv_hash_idx');
        });
    }

    public function down(): void
    {
        Schema::table('automation_workflow_versions', function (Blueprint $table): void {
            $table->dropIndex('auto_wfv_hash_idx');
            $table->dropColumn(['definition_hash', 'validation_status', 'validation_summary', 'schema_version', 'validated_at']);
        });
    }
};
