<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('visible')->default(true)->after('is_system');
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->boolean('visible')->default(true)->after('description');
        });

        // Hide admin and customer from the management UI by default.
        DB::table('roles')->whereIn('slug', ['admin', 'customer'])->update(['visible' => false]);
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn('visible');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('visible');
        });
    }
};
