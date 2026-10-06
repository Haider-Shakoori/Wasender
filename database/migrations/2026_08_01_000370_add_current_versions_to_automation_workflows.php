<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_workflows', function (Blueprint $t): void {
            $t->foreignId('current_draft_version_id')->nullable()->after('is_enabled')->constrained('automation_workflow_versions')->nullOnDelete();
            $t->foreignId('current_published_version_id')->nullable()->after('current_draft_version_id')->constrained('automation_workflow_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('automation_workflows', function (Blueprint $t): void {
            $t->dropForeign(['current_draft_version_id']);
            $t->dropForeign(['current_published_version_id']);
            $t->dropColumn(['current_draft_version_id', 'current_published_version_id']);
        });
    }
};
