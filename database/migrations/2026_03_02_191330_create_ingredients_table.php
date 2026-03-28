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
        Schema::create('ingredients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 255)->unique();
            $table->string('category', 50); // e.g., sweetener, preservative, additive
            $table->string('risk_level', 20)->nullable(); // low, medium, high
            $table->text('scientific_reference')->nullable();
            $table->text('description')->nullable();
            $table->json('aliases')->nullable(); // Other names for the same ingredient
            $table->json('health_effects')->nullable();
            $table->json('regulatory_status')->nullable(); // FDA approved, banned in EU, etc.
            $table->timestamps();

            $table->index('category');
            $table->index('risk_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};
