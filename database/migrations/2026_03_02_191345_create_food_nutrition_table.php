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
        Schema::create('food_nutrition', function (Blueprint $table) {
            $table->uuid('product_id')->primary();
            $table->decimal('calories', 10, 2)->nullable(); // per 100g/ml
            $table->decimal('fat', 10, 2)->nullable();
            $table->decimal('saturated_fat', 10, 2)->nullable();
            $table->decimal('trans_fat', 10, 2)->nullable();
            $table->decimal('cholesterol', 10, 2)->nullable();
            $table->decimal('sodium', 10, 2)->nullable();
            $table->decimal('carbohydrates', 10, 2)->nullable();
            $table->decimal('fiber', 10, 2)->nullable();
            $table->decimal('sugars', 10, 2)->nullable();
            $table->decimal('added_sugars', 10, 2)->nullable();
            $table->decimal('protein', 10, 2)->nullable();
            $table->decimal('vitamin_d', 10, 2)->nullable();
            $table->decimal('calcium', 10, 2)->nullable();
            $table->decimal('iron', 10, 2)->nullable();
            $table->decimal('potassium', 10, 2)->nullable();
            $table->json('vitamins')->nullable(); // Other vitamins
            $table->json('minerals')->nullable(); // Other minerals
            $table->string('serving_size')->nullable();
            $table->integer('servings_per_container')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->index(['calories', 'sugars', 'fat']); // For quick filtering
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('food_nutrition');
    }
};
