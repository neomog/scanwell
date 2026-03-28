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
        Schema::create('product_alternatives', function (Blueprint $table) {
            $table->uuid('product_id');
            $table->uuid('alternative_product_id');
            $table->text('reason')->nullable();
            $table->decimal('score_improvement', 5, 2)->nullable(); // How much better
            $table->json('comparison_data')->nullable(); // Detailed comparison
            $table->integer('rank')->default(1); // Which alternative is best
            $table->timestamps();

            $table->primary(['product_id', 'alternative_product_id']);
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('alternative_product_id')->references('id')->on('products')->onDelete('cascade');

            $table->index(['product_id', 'rank']);
            $table->index('score_improvement');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_alternatives');
    }
};
