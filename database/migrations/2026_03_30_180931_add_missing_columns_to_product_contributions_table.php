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
        Schema::table('product_contributions', function (Blueprint $table) {
            Schema::table('product_contributions', function (Blueprint $table) {
                $table->json('evidence')->nullable()->after('reason');
                $table->string('barcode', 50)->nullable()->after('evidence');
                $table->string('product_name', 255)->nullable()->after('barcode');

                $table->index('barcode');
                $table->index('product_name');
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_contributions', function (Blueprint $table) {
            Schema::table('product_contributions', function (Blueprint $table) {
                $table->dropColumn(['evidence', 'barcode', 'product_name']);
                $table->dropIndex(['barcode']);
                $table->dropIndex(['product_name']);
            });
        });
    }
};
