<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->boolean('is_ai')->default(false);
            $table->string('preset')->nullable();
            $table->text('custom_prompt')->nullable();
            $table->date('aspect_date_from')->nullable();
            $table->date('aspect_date_to')->nullable();
            $table->string('ai_model_name')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'is_ai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
