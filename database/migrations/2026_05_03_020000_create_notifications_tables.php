<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('created_by')->nullable();
            $table->string('type', 50)->default('announcement');
            $table->string('status', 50)->default('draft');
            $table->string('title');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->string('audience_type', 50)->default('all_users');
            $table->json('audience_filters')->nullable();
            $table->json('channels');
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('read_count')->default(0);
            $table->json('delivery_summary')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['status', 'scheduled_at']);
            $table->index(['type', 'created_at']);
        });

        Schema::create('user_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('campaign_id')->nullable();
            $table->uuid('user_id');
            $table->string('type', 50)->default('announcement');
            $table->string('title');
            $table->text('body');
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->json('channels');
            $table->json('channel_statuses')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('campaign_id')->references('id')->on('notification_campaigns')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['campaign_id', 'user_id']);
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('user_push_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->string('platform', 50);
            $table->string('token')->unique();
            $table->string('device_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_push_tokens');
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('notification_campaigns');
    }
};
