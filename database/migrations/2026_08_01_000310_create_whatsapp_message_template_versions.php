<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_message_template_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('whatsapp_message_template_id');
            $table->foreign('whatsapp_message_template_id', 'wa_tpl_ver_tpl_fk')->references('id')->on('whatsapp_message_templates')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 16);
            $table->text('body')->nullable();
            $table->text('caption')->nullable();
            $table->json('content_configuration')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->timestamps();
            $table->unique(['whatsapp_message_template_id', 'version_number'], 'wa_tpl_ver_number_uq');
            $table->index(['whatsapp_message_template_id', 'status'], 'wa_tpl_ver_status_idx');
            $table->index(['whatsapp_message_template_id', 'published_at'], 'wa_tpl_ver_pub_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_template_versions');
    }
};
