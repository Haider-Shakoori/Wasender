<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('first_name', 100)->nullable();
            $t->string('last_name', 100)->nullable();
            $t->string('display_name', 180)->nullable();
            $t->string('company', 180)->nullable();
            $t->string('email', 254)->nullable();
            $t->string('phone_input', 40);
            $t->string('phone_normalized', 16);
            $t->string('whatsapp_address', 24);
            $t->string('status', 16)->default('active');
            $t->string('consent_status', 16)->default('unknown');
            $t->string('consent_source', 24)->nullable();
            $t->timestamp('consent_recorded_at')->nullable();
            $t->timestamp('consent_expires_at')->nullable();
            $t->timestamp('opted_out_at')->nullable();
            $t->string('opt_out_source', 80)->nullable();
            $t->timestamp('suppressed_at')->nullable();
            $t->string('suppression_reason', 120)->nullable();
            $t->timestamp('blocked_at')->nullable();
            $t->string('source', 24)->default('manual');
            $t->string('source_reference', 100)->nullable();
            $t->json('custom_attributes')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['tenant_id', 'phone_normalized'], 'ct_tenant_phone_uq');
            $t->index(['tenant_id', 'status'], 'ct_tenant_status_idx');
            $t->index(['tenant_id', 'consent_status'], 'ct_tenant_consent_idx');
            $t->index(['tenant_id', 'created_at'], 'ct_tenant_time_idx');
        });
        Schema::create('contact_consent_events', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $t->string('event', 60);
            $t->string('from_status', 16)->nullable();
            $t->string('to_status', 16)->nullable();
            $t->string('source', 24);
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('reason', 500)->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('occurred_at');
            $t->timestamp('created_at')->useCurrent();
            $t->index(['contact_id', 'occurred_at'], 'ct_evt_contact_time_idx');
        });
        Schema::create('contact_groups', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name', 120);
            $t->string('description', 500)->nullable();
            $t->string('color', 24)->nullable();
            $t->boolean('is_active')->default(true);
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['tenant_id', 'name'], 'ct_group_tenant_name_uq');
        });
        Schema::create('contact_group_members', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('contact_group_id')->constrained()->cascadeOnDelete();
            $t->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $t->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['contact_group_id', 'contact_id'], 'ct_group_member_uq');
        });
        Schema::create('contact_labels', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name', 80);
            $t->string('slug', 100);
            $t->string('color', 24)->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['tenant_id', 'slug'], 'ct_label_tenant_slug_uq');
        });
        Schema::create('contact_label_assignments', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('contact_label_id')->constrained()->cascadeOnDelete();
            $t->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $t->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['contact_label_id', 'contact_id'], 'ct_label_contact_uq');
        });
        Schema::create('contact_imports', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('created_by')->constrained('users');
            $t->string('status', 24);
            $t->string('disk', 32);
            $t->string('storage_key', 255);
            $t->string('original_name', 255);
            $t->string('mime_type', 120);
            $t->unsignedBigInteger('size_bytes');
            $t->char('checksum_sha256', 64);
            $t->string('duplicate_policy', 24)->default('skip_existing');
            $t->json('mapping')->nullable();
            $t->json('preview')->nullable();
            foreach (['total_rows', 'valid_rows', 'invalid_rows', 'processed_rows', 'created_rows', 'updated_rows', 'skipped_rows', 'failed_rows'] as $c) {
                $t->unsignedInteger($c)->default(0);
            }$t->timestamp('completed_at')->nullable();
            $t->timestamp('rolled_back_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'created_at'], 'ct_import_tenant_time_idx');
        });
        Schema::create('contact_segments', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name', 120);
            $t->json('definition');
            $t->text('summary')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['tenant_id', 'name'], 'ct_segment_tenant_name_uq');
        });
    }

    public function down(): void
    {
        foreach (['contact_segments', 'contact_imports', 'contact_label_assignments', 'contact_labels', 'contact_group_members', 'contact_groups', 'contact_consent_events', 'contacts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
