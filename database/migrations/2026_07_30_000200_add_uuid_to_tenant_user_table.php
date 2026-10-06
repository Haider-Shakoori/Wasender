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
        Schema::table('tenant_user', fn (Blueprint $table) => $table->uuid('uuid')->nullable()->after('id'));
        DB::table('tenant_user')->orderBy('id')->each(
            fn (object $membership) => DB::table('tenant_user')->where('id', $membership->id)->update(['uuid' => (string) Str::uuid()])
        );
        Schema::table('tenant_user', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable(false)->change();
            $table->unique('uuid', 'tu_uuid_uq');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_user', function (Blueprint $table): void {
            $table->dropUnique('tu_uuid_uq');
            $table->dropColumn('uuid');
        });
    }
};
