<?php

use App\Services\OpenFoodFactsService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_providers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('provider_key')->unique();
            $table->string('driver');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('priority')->default(100)->index();
            $table->json('supported_families')->nullable();
            $table->json('settings')->nullable();
            $table->text('credentials')->nullable();
            $table->unsignedSmallInteger('timeout_seconds')->default(10);
            $table->unsignedSmallInteger('retry_attempts')->default(3);
            $table->unsignedInteger('cache_ttl_minutes')->default(10080);
            $table->string('health_status')->default('unknown');
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->text('last_error')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('scan_provider_lookups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('scan_provider_id')->nullable()->constrained('scan_providers')->nullOnDelete();
            $table->foreignUuid('scan_id')->nullable()->constrained('scans')->nullOnDelete();
            $table->string('barcode', 50)->index();
            $table->string('status', 30)->index();
            $table->boolean('matched')->default(false)->index();
            $table->string('product_family')->nullable()->index();
            $table->unsignedSmallInteger('confidence')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->json('response_summary')->nullable();
            $table->timestamps();
        });

        DB::table('scan_providers')->insert([
            'id' => (string) Str::uuid(),
            'name' => 'Open Facts Network',
            'provider_key' => 'open_facts',
            'driver' => OpenFoodFactsService::class,
            'is_active' => true,
            'priority' => 10,
            'supported_families' => json_encode(['food', 'cosmetic', 'pet_food', 'household', 'general']),
            'settings' => json_encode([
                'sources' => config('scanning.open_food_facts.sources', []),
            ]),
            'credentials' => null,
            'timeout_seconds' => (int) config('scanning.open_food_facts.timeout', 10),
            'retry_attempts' => (int) config('scanning.open_food_facts.retry_attempts', 3),
            'cache_ttl_minutes' => 60 * 24 * 7,
            'health_status' => 'healthy',
            'notes' => 'Default free provider backed by Open Food Facts, Open Beauty Facts, Open Product Facts, and Open Pet Food Facts.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_provider_lookups');
        Schema::dropIfExists('scan_providers');
    }
};
