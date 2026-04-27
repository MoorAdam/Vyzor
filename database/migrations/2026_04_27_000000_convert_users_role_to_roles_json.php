<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('roles')->nullable()->after('id');
        });

        foreach (DB::table('users')->select('id', 'role')->get() as $user) {
            DB::table('users')->where('id', $user->id)->update([
                'roles' => json_encode([$user->role ?: 'web']),
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('web')->after('id');
        });

        foreach (DB::table('users')->select('id', 'roles')->get() as $user) {
            $roles = json_decode($user->roles ?? '[]', true) ?: ['web'];
            DB::table('users')->where('id', $user->id)->update([
                'role' => $roles[0] ?? 'web',
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('roles');
        });
    }
};
