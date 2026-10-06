<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_workflow_step_executions', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('automation_workflow_execution_id')->constrained()->cascadeOnDelete();
            $t->foreignId('automation_workflow_step_id')->constrained()->restrictOnDelete();
            $t->string('step_key', 100);
            $t->string('step_type', 32);
            $t->string('status', 24);
            $t->unsignedSmallInteger('attempt_number');
            $t->char('idempotency_key', 64);
            $t->timestamp('started_at')->nullable();
            $t->timestamp('waiting_until')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamp('failed_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->string('failure_class', 24)->nullable();
            $t->string('failure_code', 80)->nullable();
            $t->string('failure_message', 500)->nullable();
            $t->json('input_snapshot')->nullable();
            $t->json('output_snapshot')->nullable();
            $t->string('next_step_key', 100)->nullable();
            $t->timestamps();
            $t->unique(['automation_workflow_execution_id', 'step_key', 'attempt_number'], 'auto_step_attempt_uq');
            $t->unique('idempotency_key', 'auto_step_idem_uq');
            $t->index(['automation_workflow_execution_id', 'status'], 'auto_step_exec_status_idx');
            $t->index(['status', 'waiting_until'], 'auto_step_waiting_idx');
            $t->index(['automation_workflow_execution_id', 'created_at'], 'auto_step_exec_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_workflow_step_executions');
    }
};
