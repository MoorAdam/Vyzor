<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_contexts', function (Blueprint $table) {
            $table->dropColumn(['name_hu', 'description_hu']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_contexts', function (Blueprint $table) {
            $table->string('name_hu')->nullable()->after('name');
            $table->text('description_hu')->nullable()->after('description');
        });
    }
};
