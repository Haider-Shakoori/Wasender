<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_workflows', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name', 150);
            $t->text('description')->nullable();
            $t->string('status', 20);
            $t->boolean('is_enabled')->default(false);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            foreach (['published_at', 'enabled_at', 'disabled_at', 'archived_at'] as $x) {
                $t->timestamp($x)->nullable();
            }$t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->softDeletes();
            $t->index(['tenant_id', 'status'], 'auto_wf_tenant_status_idx');
            $t->index(['tenant_id', 'is_enabled'], 'auto_wf_tenant_enabled_idx');
            $t->index(['tenant_id', 'updated_at'], 'auto_wf_tenant_updated_idx');
            $t->index(['tenant_id', 'name'], 'auto_wf_tenant_name_idx');
        });
        Schema::create('automation_workflow_versions', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('automation_workflow_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version_number');
            $t->string('status', 20);
            $t->string('trigger_type', 40)->nullable();
            $t->json('trigger_configuration')->nullable();
            $t->json('settings')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('published_at')->nullable();
            $t->timestamp('superseded_at')->nullable();
            $t->timestamps();
            $t->unique(['automation_workflow_id', 'version_number'], 'auto_wfv_number_uq');
            $t->index(['automation_workflow_id', 'status'], 'auto_wfv_status_idx');
        });
        Schema::create('automation_workflow_steps', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('automation_workflow_version_id')->constrained()->cascadeOnDelete();
            $t->string('step_key', 80);
            $t->string('parent_step_key', 80)->nullable();
            $t->string('step_type', 20);
            $t->unsignedSmallInteger('position');
            $t->string('name', 150)->nullable();
            $t->json('configuration')->nullable();
            $t->timestamps();
            $t->unique(['automation_workflow_version_id', 'step_key'], 'auto_wfs_key_uq');
            $t->index(['automation_workflow_version_id', 'position'], 'auto_wfs_position_idx');
            $t->index(['automation_workflow_version_id', 'step_type'], 'auto_wfs_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_workflow_steps');
        Schema::dropIfExists('automation_workflow_versions');
        Schema::dropIfExists('automation_workflows');
    }
};
