<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_message_attachments', function (Blueprint $table): void {
            $table->dropUnique(['storage_key']);
            $table->index('storage_key', 'wa_msg_att_storage_idx');
        });
        Schema::table('whatsapp_campaign_attachments', function (Blueprint $table): void {
            $table->dropUnique(['storage_key']);
            $table->index('storage_key', 'wa_cmp_att_storage_idx');
        });
        Schema::table('whatsapp_campaigns', function (Blueprint $table): void {
            $table->foreignId('message_template_id')->nullable()->after('body')->constrained('whatsapp_message_templates')->nullOnDelete();
            $table->foreignId('message_template_version_id')->nullable()->after('message_template_id')->constrained('whatsapp_message_template_versions')->nullOnDelete();
            $table->uuid('template_uuid')->nullable()->after('message_template_version_id');
            $table->uuid('template_version_uuid')->nullable()->after('template_uuid');
            $table->unsignedInteger('template_version_number')->nullable()->after('template_version_uuid');
            $table->char('template_content_hash', 64)->nullable()->after('template_version_number');
            $table->json('template_variable_values')->nullable()->after('template_content_hash');
            $table->boolean('template_content_customized')->default(false)->after('template_variable_values');
            $table->timestamp('template_rendered_at')->nullable()->after('template_content_customized');
            $table->index(['tenant_id', 'template_uuid'], 'wa_cmp_tenant_tpl_idx');
        });

        Schema::table('whatsapp_messages', function (Blueprint $table): void {
            $table->foreignId('message_template_id')->nullable()->after('body')->constrained('whatsapp_message_templates')->nullOnDelete();
            $table->foreignId('message_template_version_id')->nullable()->after('message_template_id')->constrained('whatsapp_message_template_versions')->nullOnDelete();
            $table->uuid('template_uuid')->nullable()->after('message_template_version_id');
            $table->uuid('template_version_uuid')->nullable()->after('template_uuid');
            $table->unsignedInteger('template_version_number')->nullable()->after('template_version_uuid');
            $table->char('template_content_hash', 64)->nullable()->after('template_version_number');
            $table->char('template_render_hash', 64)->nullable()->after('template_content_hash');
            $table->json('template_variable_values')->nullable()->after('template_render_hash');
            $table->timestamp('template_rendered_at')->nullable()->after('template_variable_values');
            $table->index(['tenant_id', 'template_uuid'], 'wa_msg_tenant_tpl_idx');
        });

        Schema::table('whatsapp_campaign_recipients', function (Blueprint $table): void {
            $table->text('rendered_body')->nullable()->after('snapshot_data');
            $table->text('rendered_caption')->nullable()->after('rendered_body');
            $table->char('template_render_hash', 64)->nullable()->after('rendered_caption');
        });

        Schema::create('whatsapp_message_template_usages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_message_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('whatsapp_message_template_version_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('template_uuid');
            $table->uuid('template_version_uuid');
            $table->string('usage_type', 24);
            $table->uuid('usage_uuid');
            $table->timestamps();
            $table->unique(['usage_type', 'usage_uuid'], 'wa_tpl_usage_subject_uq');
            $table->index(['tenant_id', 'template_version_uuid'], 'wa_tpl_usage_version_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_template_usages');
        Schema::table('whatsapp_campaign_recipients', fn (Blueprint $table) => $table->dropColumn(['rendered_body', 'rendered_caption', 'template_render_hash']));
        Schema::table('whatsapp_messages', function (Blueprint $table): void {
            $table->dropForeign(['message_template_id']);
            $table->dropForeign(['message_template_version_id']);
            $table->dropColumn(['message_template_id', 'message_template_version_id', 'template_uuid', 'template_version_uuid', 'template_version_number', 'template_content_hash', 'template_render_hash', 'template_variable_values', 'template_rendered_at']);
        });
        Schema::table('whatsapp_campaigns', function (Blueprint $table): void {
            $table->dropForeign(['message_template_id']);
            $table->dropForeign(['message_template_version_id']);
            $table->dropColumn(['message_template_id', 'message_template_version_id', 'template_uuid', 'template_version_uuid', 'template_version_number', 'template_content_hash', 'template_variable_values', 'template_content_customized', 'template_rendered_at']);
        });
        Schema::table('whatsapp_message_attachments', function (Blueprint $table): void {
            $table->dropIndex('wa_msg_att_storage_idx');
            $table->unique('storage_key');
        });
        Schema::table('whatsapp_campaign_attachments', function (Blueprint $table): void {
            $table->dropIndex('wa_cmp_att_storage_idx');
            $table->unique('storage_key');
        });
    }
};
