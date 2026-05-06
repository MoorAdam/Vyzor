<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'ga_property_id')) {
                $table->text('ga_property_id')->nullable();
            }
            if (!Schema::hasColumn('projects', 'ga_last_verified_at')) {
                $table->timestamp('ga_last_verified_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $columns = array_filter(
                ['ga_property_id', 'ga_last_verified_at'],
                fn ($c) => Schema::hasColumn('projects', $c),
            );
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
