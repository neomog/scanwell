<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->uuid('user_id')->primary();
            $table->string('diet_type', 50)->nullable();
            $table->json('allergies')->nullable();
            $table->string('skin_type', 50)->nullable();
            $table->json('health_goals')->nullable();
            $table->json('avoid_ingredients')->nullable();
            $table->integer('min_score_threshold')->default(50);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
