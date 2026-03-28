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
        Schema::create('product_ingredients', function (Blueprint $table) {
            $table->uuid('product_id');
            $table->uuid('ingredient_id');
            $table->decimal('percentage', 5, 2)->nullable(); // Percentage in product
            $table->boolean('is_additive')->default(false);
            $table->string('origin')->nullable(); // natural, synthetic, etc.
            $table->timestamps();

            $table->primary(['product_id', 'ingredient_id']);
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('ingredient_id')->references('id')->on('ingredients')->onDelete('cascade');

            $table->index(['product_id', 'percentage']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_ingredients');
    }
};
