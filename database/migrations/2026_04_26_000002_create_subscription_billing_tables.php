<?php

use App\Models\Subscription;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('subscription_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('amount')->default(0);
            $table->string('currency', 3)->default('usd');
            $table->string('billing_interval')->default('month');
            $table->unsignedInteger('billing_interval_count')->default(1);
            $table->unsignedInteger('trial_days')->default(0);
            $table->string('stripe_price_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->foreignId('plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->foreignId('price_id')->nullable()->constrained('subscription_prices')->nullOnDelete();
            $table->string('provider')->default('system');
            $table->string('status', 50)->default(Subscription::STATUS_ACTIVE);
            $table->unsignedInteger('quantity')->default(1);
            $table->string('currency', 3)->default('usd');
            $table->unsignedInteger('amount')->default(0);
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->nullable()->unique();
            $table->string('stripe_checkout_session_id')->nullable();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('stripe_invoice_id')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('current_period_starts_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'status']);
            $table->index(['plan_id', 'status']);
        });

        $this->seedDefaults();
        $this->backfillExistingUsers();
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_prices');
        Schema::dropIfExists('subscription_plans');
    }

    protected function seedDefaults(): void
    {
        $timestamp = now();
        $planIds = [];

        foreach (config('subscriptions.defaults', []) as $plan) {
            $planIds[$plan['slug']] = DB::table('subscription_plans')->insertGetId([
                'slug' => $plan['slug'],
                'name' => $plan['name'],
                'description' => $plan['description'] ?? null,
                'features' => json_encode($plan['features'] ?? []),
                'is_active' => true,
                'is_default' => (bool) ($plan['is_default'] ?? false),
                'display_order' => (int) ($plan['display_order'] ?? 0),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        foreach (config('subscriptions.defaults', []) as $plan) {
            foreach ($plan['prices'] ?? [] as $price) {
                DB::table('subscription_prices')->insert([
                    'plan_id' => $planIds[$plan['slug']],
                    'name' => $price['name'],
                    'amount' => (int) ($price['amount'] ?? 0),
                    'currency' => strtolower((string) ($price['currency'] ?? 'usd')),
                    'billing_interval' => $price['billing_interval'] ?? 'month',
                    'billing_interval_count' => (int) ($price['billing_interval_count'] ?? 1),
                    'trial_days' => (int) ($price['trial_days'] ?? 0),
                    'stripe_price_id' => $price['stripe_price_id'] ?? null,
                    'is_active' => true,
                    'is_default' => (bool) ($price['is_default'] ?? false),
                    'metadata' => isset($price['metadata']) ? json_encode($price['metadata']) : null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }
        }
    }

    protected function backfillExistingUsers(): void
    {
        $defaultSlug = config('subscriptions.default_slug', 'free');
        $planId = DB::table('subscription_plans')->where('slug', $defaultSlug)->value('id');
        $priceId = DB::table('subscription_prices')
            ->where('plan_id', $planId)
            ->where('is_default', true)
            ->value('id');

        if (!$planId) {
            return;
        }

        DB::table('users')
            ->select(['id', 'created_at'])
            ->orderBy('created_at')
            ->chunk(100, function ($users) use ($planId, $priceId) {
                $rows = [];
                $timestamp = now();

                foreach ($users as $user) {
                    $rows[] = [
                        'id' => (string) Str::uuid(),
                        'user_id' => $user->id,
                        'plan_id' => $planId,
                        'price_id' => $priceId,
                        'provider' => 'system',
                        'status' => Subscription::STATUS_ACTIVE,
                        'quantity' => 1,
                        'currency' => 'usd',
                        'amount' => 0,
                        'starts_at' => $user->created_at ?? $timestamp,
                        'current_period_starts_at' => $user->created_at ?? $timestamp,
                        'current_period_ends_at' => null,
                        'metadata' => json_encode([
                            'assigned_reason' => 'migration_backfill',
                        ]),
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }

                if ($rows !== []) {
                    DB::table('subscriptions')->insert($rows);
                }
            });
    }
};
