<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->after('id');
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('suspended_at')->nullable();
            $table->foreignId('suspended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('suspension_reason', 500)->nullable();
        });
        DB::table('users')->whereNull('uuid')->orderBy('id')->eachById(
            fn ($user) => DB::table('users')->where('id', $user->id)->update(['uuid' => (string) Str::uuid()])
        );
        Schema::table('users', fn (Blueprint $table) => $table->unique('uuid', 'users_uuid_uq'));

        Schema::table('tenants', function (Blueprint $table): void {
            $table->timestamp('suspended_at')->nullable();
            $table->foreignId('suspended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('suspension_reason', 500)->nullable();
        });

        Schema::create('platform_permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('platform_roles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(true);
            $table->timestamps();
        });
        Schema::create('platform_permission_role', function (Blueprint $table): void {
            $table->foreignId('platform_role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['platform_role_id', 'platform_permission_id'], 'platform_permission_role_pk');
        });
        Schema::create('platform_role_user', function (Blueprint $table): void {
            $table->foreignId('platform_role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['platform_role_id', 'user_id'], 'platform_role_user_pk');
            $table->index('user_id');
        });
        Schema::create('platform_notes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->morphs('subject');
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamps();
        });
        Schema::create('platform_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100)->index();
            $table->nullableMorphs('subject');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id', 'created_at'], 'platform_audit_subject_idx');
        });
        Schema::create('system_heartbeats', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->timestamp('ran_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_heartbeats');
        Schema::dropIfExists('platform_audit_logs');
        Schema::dropIfExists('platform_notes');
        Schema::dropIfExists('platform_role_user');
        Schema::dropIfExists('platform_permission_role');
        Schema::dropIfExists('platform_roles');
        Schema::dropIfExists('platform_permissions');
        Schema::table('tenants', fn (Blueprint $table) => $table->dropConstrainedForeignId('suspended_by'));
        Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn(['suspended_at', 'suspension_reason']));
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('suspended_by'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['uuid', 'status', 'suspended_at', 'suspension_reason']));
    }
};
