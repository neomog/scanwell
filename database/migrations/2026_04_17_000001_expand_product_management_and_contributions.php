<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('product_family', 50)->nullable()->after('category_id');
            $table->string('category_name', 255)->nullable()->after('product_family');
            $table->text('ingredients_text')->nullable()->after('image_url');
            $table->json('additives')->nullable()->after('ingredients_text');
            $table->json('allergens')->nullable()->after('additives');
            $table->json('region_availability')->nullable()->after('allergens');
            $table->json('manual_overrides')->nullable()->after('region_availability');
            $table->uuid('created_by')->nullable()->after('manual_overrides');
            $table->uuid('approved_by')->nullable()->after('created_by');
            $table->timestamp('approved_at')->nullable()->after('approved_by');

            $table->index('product_family');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('product_contributions', function (Blueprint $table) {
            $table->string('field_name', 255)->nullable()->after('change_type');
            $table->json('moderated_data')->nullable()->after('new_data');
            $table->text('flag_reason')->nullable()->after('review_notes');
            $table->timestamp('flagged_at')->nullable()->after('flag_reason');
            $table->integer('reputation_points_awarded')->default(0)->after('flagged_at');
            $table->json('meta')->nullable()->after('reputation_points_awarded');

            $table->index(['status', 'barcode']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->integer('reputation_points')->default(0)->after('role');
            $table->unsignedInteger('approved_contributions_count')->default(0)->after('reputation_points');
            $table->unsignedInteger('rejected_contributions_count')->default(0)->after('approved_contributions_count');
        });

        Schema::create('product_barcodes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->string('barcode', 50)->unique();
            $table->string('label', 100)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['product_id', 'is_primary']);
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->string('disk', 50)->nullable();
            $table->string('path')->nullable();
            $table->text('url')->nullable();
            $table->string('source', 50)->default('manual');
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->uuid('uploaded_by')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['product_id', 'is_primary']);
        });

        Schema::create('product_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('actor_id')->nullable();
            $table->uuid('product_id')->nullable();
            $table->uuid('contribution_id')->nullable();
            $table->string('action', 100);
            $table->string('description')->nullable();
            $table->json('changes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('contribution_id')->references('id')->on('product_contributions')->nullOnDelete();
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_audit_logs');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_barcodes');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'reputation_points',
                'approved_contributions_count',
                'rejected_contributions_count',
            ]);
        });

        Schema::table('product_contributions', function (Blueprint $table) {
            $table->dropIndex(['status', 'barcode']);
            $table->dropColumn([
                'field_name',
                'moderated_data',
                'flag_reason',
                'flagged_at',
                'reputation_points_awarded',
                'meta',
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['approved_by']);
            $table->dropIndex(['product_family']);
            $table->dropColumn([
                'product_family',
                'category_name',
                'ingredients_text',
                'additives',
                'allergens',
                'region_availability',
                'manual_overrides',
                'created_by',
                'approved_by',
                'approved_at',
            ]);
        });
    }
};
