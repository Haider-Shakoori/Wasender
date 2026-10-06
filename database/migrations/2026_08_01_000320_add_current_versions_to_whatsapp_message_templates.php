<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_message_templates', function (Blueprint $table): void {
            $table->unsignedBigInteger('current_draft_version_id')->nullable()->after('status');
            $table->unsignedBigInteger('current_published_version_id')->nullable()->after('current_draft_version_id');
            $table->foreign('current_draft_version_id', 'wa_tpl_current_draft_fk')->references('id')->on('whatsapp_message_template_versions')->nullOnDelete();
            $table->foreign('current_published_version_id', 'wa_tpl_current_pub_fk')->references('id')->on('whatsapp_message_template_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_message_templates', function (Blueprint $table): void {
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                $table->dropForeign(['current_published_version_id']);
                $table->dropForeign(['current_draft_version_id']);
            } else {
                $table->dropForeign('wa_tpl_current_pub_fk');
                $table->dropForeign('wa_tpl_current_draft_fk');
            }
            $table->dropColumn(['current_published_version_id', 'current_draft_version_id']);
        });
    }
};
