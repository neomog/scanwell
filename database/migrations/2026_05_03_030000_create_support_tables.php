<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_cases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference')->unique();
            $table->uuid('user_id');
            $table->uuid('assigned_to')->nullable();
            $table->string('type', 50)->default('ticket');
            $table->string('subject');
            $table->text('description');
            $table->string('status', 50)->default('open');
            $table->string('priority', 50)->default('normal');
            $table->string('source', 50)->default('mobile');
            $table->json('attachments')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('customer_last_read_at')->nullable();
            $table->timestamp('support_last_read_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->index(['type', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('support_case_id');
            $table->uuid('user_id')->nullable();
            $table->string('sender_type', 50)->default('customer');
            $table->text('message');
            $table->json('attachments')->nullable();
            $table->boolean('is_internal')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('support_case_id')->references('id')->on('support_cases')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['support_case_id', 'created_at']);
            $table->index(['sender_type', 'is_internal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_cases');
    }
};
