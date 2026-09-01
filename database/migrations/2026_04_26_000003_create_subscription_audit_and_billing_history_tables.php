<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('subscription_id')->nullable();
            $table->string('event_type', 100);
            $table->string('source', 50)->default('system');
            $table->uuid('actor_id')->nullable();
            $table->foreignId('from_plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->foreignId('to_plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->foreignId('from_price_id')->nullable()->constrained('subscription_prices')->nullOnDelete();
            $table->foreignId('to_price_id')->nullable()->constrained('subscription_prices')->nullOnDelete();
            $table->string('status_before', 50)->nullable();
            $table->string('status_after', 50)->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->nullOnDelete();
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['user_id', 'created_at']);
            $table->index(['subscription_id', 'created_at']);
            $table->index(['event_type', 'created_at']);
        });

        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('subscription_id')->nullable();
            $table->string('provider', 50)->default('stripe');
            $table->string('provider_invoice_id')->unique();
            $table->string('provider_payment_intent_id')->nullable()->index();
            $table->string('provider_charge_id')->nullable()->index();
            $table->string('currency', 3)->default('usd');
            $table->unsignedInteger('subtotal')->default(0);
            $table->unsignedInteger('tax')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('amount_paid')->default(0);
            $table->unsignedInteger('amount_due')->default(0);
            $table->string('status', 50)->default('draft');
            $table->string('billing_reason', 100)->nullable();
            $table->text('hosted_invoice_url')->nullable();
            $table->text('invoice_pdf_url')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->nullOnDelete();
            $table->index(['user_id', 'status']);
            $table->index(['subscription_id', 'status']);
        });

        Schema::create('billing_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('subscription_id')->nullable();
            $table->uuid('billing_invoice_id')->nullable();
            $table->string('provider', 50)->default('stripe');
            $table->string('provider_transaction_id')->nullable()->unique();
            $table->string('type', 50);
            $table->string('status', 50)->default('pending');
            $table->unsignedInteger('amount')->default(0);
            $table->string('currency', 3)->default('usd');
            $table->string('description')->nullable();
            $table->string('payment_method_brand', 50)->nullable();
            $table->string('payment_method_last4', 4)->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->nullOnDelete();
            $table->foreign('billing_invoice_id')->references('id')->on('billing_invoices')->nullOnDelete();
            $table->index(['user_id', 'status']);
            $table->index(['billing_invoice_id', 'status']);
            $table->index(['type', 'status']);
        });

        Schema::create('billing_refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('subscription_id')->nullable();
            $table->uuid('billing_invoice_id')->nullable();
            $table->uuid('billing_transaction_id')->nullable();
            $table->string('provider', 50)->default('stripe');
            $table->string('provider_refund_id')->unique();
            $table->unsignedInteger('amount')->default(0);
            $table->string('currency', 3)->default('usd');
            $table->string('reason', 100)->nullable();
            $table->string('status', 50)->default('pending');
            $table->string('requested_by_type', 50)->nullable();
            $table->string('requested_by_id')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->nullOnDelete();
            $table->foreign('billing_invoice_id')->references('id')->on('billing_invoices')->nullOnDelete();
            $table->foreign('billing_transaction_id')->references('id')->on('billing_transactions')->nullOnDelete();
            $table->index(['user_id', 'status']);
            $table->index(['billing_invoice_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_refunds');
        Schema::dropIfExists('billing_transactions');
        Schema::dropIfExists('billing_invoices');
        Schema::dropIfExists('subscription_events');
    }
};
