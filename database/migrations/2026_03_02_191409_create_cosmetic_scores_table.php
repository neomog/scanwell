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
        Schema::create('cosmetic_scores', function (Blueprint $table) {
            $table->uuid('product_id')->primary();
            $table->decimal('overall_score', 5, 2); // 0-100
            $table->decimal('irritant_score', 5, 2);
            $table->decimal('endocrine_score', 5, 2);
            $table->decimal('allergen_score', 5, 2);
            $table->decimal('environmental_score', 5, 2);
            $table->json('score_breakdown')->nullable();
            $table->text('explanation_text')->nullable();
            $table->json('warnings')->nullable();
            $table->json('benefits')->nullable();
            $table->json('skin_types_suitable')->nullable(); // Which skin types it's good for
            $table->timestamp('calculated_at')->useCurrent();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->index('overall_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cosmetic_scores');
    }
};
