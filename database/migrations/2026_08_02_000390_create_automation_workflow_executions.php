<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_workflow_executions', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('automation_workflow_id')->constrained()->restrictOnDelete();
            $t->foreignId('automation_workflow_version_id')->constrained()->restrictOnDelete();
            $t->string('status', 24);
            $t->string('trigger_type', 64);
            $t->string('trigger_reference', 191)->nullable();
            $t->char('definition_hash', 64);
            $t->string('idempotency_key', 191);
            $t->string('current_step_key', 100)->nullable();
            $t->json('context')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('waiting_until')->nullable();
            $t->timestamp('cancel_requested_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamp('failed_at')->nullable();
            $t->timestamp('timed_out_at')->nullable();
            $t->string('failure_code', 80)->nullable();
            $t->string('failure_message', 500)->nullable();
            $t->unsignedInteger('processed_steps')->default(0);
            $t->unsignedInteger('maximum_steps');
            $t->unsignedInteger('maximum_execution_minutes');
            $t->unsignedInteger('attempt_count')->default(0);
            $t->timestamp('last_heartbeat_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'idempotency_key'], 'auto_exec_tenant_idem_uq');
            $t->index(['automation_workflow_id', 'status'], 'auto_exec_workflow_status_idx');
            $t->index(['tenant_id', 'status'], 'auto_exec_tenant_status_idx');
            $t->index(['status', 'waiting_until'], 'auto_exec_waiting_idx');
            $t->index(['status', 'last_heartbeat_at'], 'auto_exec_heartbeat_idx');
            $t->index(['tenant_id', 'created_at'], 'auto_exec_tenant_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_workflow_executions');
    }
};
