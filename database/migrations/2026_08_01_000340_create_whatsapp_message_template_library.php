<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_message_template_categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 120);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'slug'], 'wa_tpl_cat_tenant_slug_uq');
        });
        Schema::create('whatsapp_message_template_labels', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('slug', 100);
            $table->string('color', 24)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'slug'], 'wa_tpl_lbl_tenant_slug_uq');
        });
        Schema::table('whatsapp_message_templates', function (Blueprint $table): void {
            $table->foreignId('category_id')->nullable()->after('tenant_id')->constrained('whatsapp_message_template_categories')->nullOnDelete();
            $table->index(['tenant_id', 'category_id'], 'wa_tpl_tenant_cat_idx');
        });
        Schema::create('whatsapp_message_template_label_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_message_template_id');
            $table->foreign('whatsapp_message_template_id', 'wa_tpl_lbl_asn_tpl_fk')->references('id')->on('whatsapp_message_templates')->cascadeOnDelete();
            $table->foreignId('whatsapp_message_template_label_id');
            $table->foreign('whatsapp_message_template_label_id', 'wa_tpl_lbl_asn_lbl_fk')->references('id')->on('whatsapp_message_template_labels')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['whatsapp_message_template_id', 'whatsapp_message_template_label_id'], 'wa_tpl_lbl_asn_uq');
            $table->index(['tenant_id', 'whatsapp_message_template_label_id'], 'wa_tpl_lbl_filter_idx');
        });
        Schema::create('whatsapp_message_template_attachments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_message_template_id');
            $table->foreign('whatsapp_message_template_id', 'wa_tpl_att_tpl_fk')->references('id')->on('whatsapp_message_templates')->cascadeOnDelete();
            $table->foreignId('whatsapp_message_template_version_id');
            $table->foreign('whatsapp_message_template_version_id', 'wa_tpl_att_ver_fk')->references('id')->on('whatsapp_message_template_versions')->cascadeOnDelete();
            $table->string('disk', 32);
            $table->string('storage_key');
            $table->string('original_name', 255);
            $table->string('safe_name', 180);
            $table->string('mime_type', 120);
            $table->string('extension', 12)->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64);
            $table->string('media_category', 16);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['whatsapp_message_template_version_id', 'deleted_at'], 'wa_tpl_att_ver_active_idx');
            $table->index(['tenant_id', 'media_category'], 'wa_tpl_att_tenant_media_idx');
            $table->index('storage_key', 'wa_tpl_att_storage_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_template_attachments');
        Schema::dropIfExists('whatsapp_message_template_label_assignments');
        Schema::table('whatsapp_message_templates', function (Blueprint $table): void {
            $table->dropForeign(['category_id']);
            $table->dropIndex('wa_tpl_tenant_cat_idx');
            $table->dropColumn('category_id');
        });
        Schema::dropIfExists('whatsapp_message_template_labels');
        Schema::dropIfExists('whatsapp_message_template_categories');
    }
};
