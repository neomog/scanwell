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
        Schema::create('food_scores', function (Blueprint $table) {
            $table->uuid('product_id')->primary();
            $table->decimal('overall_score', 5, 2); // 0-100
            $table->decimal('nutrition_score', 5, 2);
            $table->decimal('ingredient_score', 5, 2);
            $table->decimal('additive_score', 5, 2);
            $table->decimal('processing_score', 5, 2);
            $table->string('nova_group', 20)->nullable(); // NOVA classification
            $table->string('nutriscore_grade', 1)->nullable(); // A, B, C, D, E
            $table->json('score_breakdown')->nullable(); // Detailed breakdown
            $table->text('explanation_text')->nullable();
            $table->json('warnings')->nullable(); // Specific warnings
            $table->json('benefits')->nullable(); // Positive aspects
            $table->timestamp('calculated_at')->useCurrent();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->index('overall_score');
            $table->index('nova_group');
            $table->index('nutriscore_grade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('food_scores');
    }
};
