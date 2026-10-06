<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_message_template_versions', function (Blueprint $table): void {
            $table->string('variable_context', 16)->default('contact')->after('content_configuration');
            $table->json('variable_configuration')->nullable()->after('variable_context');
            $table->char('content_hash', 64)->nullable()->after('variable_configuration');
            $table->unsignedSmallInteger('parser_version')->default(1)->after('content_hash');
            $table->unsignedSmallInteger('renderer_version')->default(1)->after('parser_version');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_message_template_versions', function (Blueprint $table): void {
            $table->dropColumn(['variable_context', 'variable_configuration', 'content_hash', 'parser_version', 'renderer_version']);
        });
    }
};
