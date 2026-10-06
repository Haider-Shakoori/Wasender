<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->after('id');
        });

        DB::table('roles')->orderBy('id')->each(function (object $role): void {
            DB::table('roles')->where('id', $role->id)->update(['uuid' => (string) Str::uuid()]);
        });

        Schema::table('roles', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable(false)->change();
            $table->unique('uuid', 'roles_uuid_uq');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropUnique('roles_uuid_uq');
            $table->dropColumn('uuid');
        });
    }
};
