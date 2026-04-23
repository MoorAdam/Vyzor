<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('project_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->json('collaborators')->nullable();
            $table->timestamps();
        });

        // Migrate existing owner_id data from projects
        DB::statement('
            INSERT INTO project_permissions (project_id, owner_id, created_at, updated_at)
            SELECT id, owner_id, NOW(), NOW() FROM projects
        ');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropColumn('owner_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->constrained('users')->cascadeOnDelete();
        });

        // Restore owner_id from project_permissions
        DB::statement('
            UPDATE projects
            SET owner_id = (
                SELECT owner_id FROM project_permissions
                WHERE project_permissions.project_id = projects.id
                LIMIT 1
            )
        ');

        Schema::dropIfExists('project_permissions');
    }
};
