<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ImportScanProviderProducts;
use App\Models\ScanProvider;
use App\Models\ScanProviderLookup;
use App\Services\ScanProviderImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScanProviderController extends Controller
{
    public function index(ScanProviderImportService $importService): View
    {
        $providers = ScanProvider::query()
            ->withCount([
                'lookups',
                'lookups as successful_lookups_count' => fn ($query) => $query->where('status', 'success'),
                'lookups as failed_lookups_count' => fn ($query) => $query->where('status', 'error'),
                'lookups as recent_lookups_count' => fn ($query) => $query->where('created_at', '>=', now()->subDays(7)),
                'lookups as recent_successful_lookups_count' => fn ($query) => $query
                    ->where('created_at', '>=', now()->subDays(7))
                    ->where('status', 'success'),
            ])
            ->orderBy('priority')
            ->orderBy('name')
            ->get();

        $providers->each(function (ScanProvider $provider) use ($importService): void {
            $provider->setAttribute('supports_import', $importService->supportsImport($provider));
            $provider->setAttribute('import_config_summary', $importService->importConfigSummary($provider));
        });

        $recentLookups = ScanProviderLookup::query()
            ->with('provider')
            ->latest()
            ->limit(20)
            ->get();

        return view('admin.scanning.index', compact('providers', 'recentLookups'));
    }

    public function update(Request $request, ScanProvider $scanProvider): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'priority' => 'required|integer|min:1|max:9999',
            'supported_families' => 'nullable|string',
            'timeout_seconds' => 'required|integer|min:1|max:60',
            'retry_attempts' => 'required|integer|min:0|max:10',
            'cache_ttl_minutes' => 'required|integer|min:0|max:43200',
            'import_query' => 'nullable|string|max:255',
            'import_page_size' => 'nullable|integer|min:1|max:100',
            'import_max_pages' => 'nullable|integer|min:1|max:20',
            'open_food_facts_base_url' => 'nullable|url|max:255',
            'open_beauty_facts_base_url' => 'nullable|url|max:255',
            'open_product_facts_base_url' => 'nullable|url|max:255',
            'open_pet_food_facts_base_url' => 'nullable|url|max:255',
            'upcitemdb_mode' => 'nullable|string|in:trial,prod',
            'upcitemdb_base_url' => 'nullable|url|max:255',
            'upcitemdb_key_type' => 'nullable|string|max:50',
            'upcitemdb_user_key' => 'nullable|string|max:255',
            'barcode_lookup_base_url' => 'nullable|url|max:255',
            'barcode_lookup_api_key' => 'nullable|string|max:255',
            'edamam_base_url' => 'nullable|url|max:255',
            'edamam_category' => 'nullable|string|max:100',
            'edamam_nutrition_type' => 'nullable|string|max:100',
            'edamam_app_id' => 'nullable|string|max:255',
            'edamam_app_key' => 'nullable|string|max:255',
            'gs1_us_base_url' => 'nullable|url|max:255',
            'gs1_us_http_method' => 'nullable|string|in:GET,POST',
            'gs1_us_barcode_field' => 'nullable|string|max:100',
            'gs1_us_barcode_path' => 'nullable|string|max:100',
            'gs1_us_api_key' => 'nullable|string|max:255',
            'gs1_us_account_id' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $settings = $this->buildProviderSettings($scanProvider, $validated);
        $credentials = $this->buildProviderCredentials($scanProvider, $validated);

        $scanProvider->update([
            'name' => $validated['name'],
            'priority' => $validated['priority'],
            'supported_families' => $this->parseFamilies($validated['supported_families'] ?? ''),
            'timeout_seconds' => $validated['timeout_seconds'],
            'retry_attempts' => $validated['retry_attempts'],
            'cache_ttl_minutes' => $validated['cache_ttl_minutes'],
            'settings' => $settings,
            'credentials' => $credentials,
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', "{$scanProvider->name} updated.");
    }

    public function toggleActive(ScanProvider $scanProvider): RedirectResponse
    {
        $scanProvider->update([
            'is_active' => ! $scanProvider->is_active,
        ]);

        return back()->with(
            'success',
            $scanProvider->is_active
                ? "{$scanProvider->name} is now active."
                : "{$scanProvider->name} has been disabled."
        );
    }

    public function syncProducts(ScanProvider $scanProvider, ScanProviderImportService $importService): RedirectResponse
    {
        if (!$importService->supportsImport($scanProvider)) {
            return back()->with('error', "{$scanProvider->name} does not support one-click catalog imports yet.");
        }

        if ($importService->importConfigSummary($scanProvider) === null) {
            return back()->with('error', "Add settings.import.query to {$scanProvider->name} before running sync.");
        }

        ImportScanProviderProducts::dispatch($scanProvider->id, request()->user()?->id);

        return back()->with('success', "{$scanProvider->name} sync has been queued.");
    }

    protected function parseFamilies(string $value): array
    {
        return collect(preg_split('/[\s,]+/', $value))
            ->map(fn (?string $family) => trim((string) $family))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function buildProviderSettings(ScanProvider $scanProvider, array $validated): array
    {
        $settings = $scanProvider->settings ?? [];

        switch ($scanProvider->provider_key) {
            case 'open_facts':
                $settings['sources'] = [
                    [
                        'key' => 'open_food_facts',
                        'base_url' => $validated['open_food_facts_base_url'] ?: 'https://world.openfoodfacts.org/api/v2',
                        'family_hint' => 'food',
                        'enabled' => $this->sourceEnabled($scanProvider, 'open_food_facts', true),
                    ],
                    [
                        'key' => 'open_beauty_facts',
                        'base_url' => $validated['open_beauty_facts_base_url'] ?: 'https://world.openbeautyfacts.org/api/v2',
                        'family_hint' => 'cosmetic',
                        'enabled' => $this->sourceEnabled($scanProvider, 'open_beauty_facts', true),
                    ],
                    [
                        'key' => 'open_product_facts',
                        'base_url' => $validated['open_product_facts_base_url'] ?: 'https://world.openproductfacts.org/api/v2',
                        'family_hint' => 'general',
                        'enabled' => $this->sourceEnabled($scanProvider, 'open_product_facts', false),
                    ],
                    [
                        'key' => 'open_pet_food_facts',
                        'base_url' => $validated['open_pet_food_facts_base_url'] ?: 'https://world.openpetfoodfacts.org/api/v2',
                        'family_hint' => 'pet_food',
                        'enabled' => $this->sourceEnabled($scanProvider, 'open_pet_food_facts', true),
                    ],
                ];
                break;
            case 'upcitemdb':
                $settings['mode'] = $validated['upcitemdb_mode'] ?: 'trial';
                $settings['base_url'] = $validated['upcitemdb_base_url']
                    ?: ($settings['mode'] === 'prod'
                        ? 'https://api.upcitemdb.com/prod/v1/lookup'
                        : 'https://api.upcitemdb.com/prod/trial/lookup');
                $settings['key_type'] = $validated['upcitemdb_key_type'] ?: '3scale';
                break;
            case 'barcode_lookup':
                $settings['base_url'] = $validated['barcode_lookup_base_url'] ?: 'https://api.barcodelookup.com/v3/products';
                break;
            case 'edamam':
                $settings['base_url'] = $validated['edamam_base_url'] ?: 'https://api.edamam.com/api/food-database/v2/parser';
                $settings['category'] = $validated['edamam_category'] ?: 'packaged-foods';
                $settings['nutrition_type'] = $validated['edamam_nutrition_type'] ?: 'cooking';
                break;
            case 'gs1_us':
                $settings['base_url'] = $validated['gs1_us_base_url'] ?? '';
                $settings['http_method'] = $validated['gs1_us_http_method'] ?: 'GET';
                $settings['barcode_field'] = $validated['gs1_us_barcode_field'] ?: 'gtin';
                $settings['barcode_path'] = $validated['gs1_us_barcode_path'] ?: 'gtin';
                break;
        }

        return $this->mergeImportSettings($settings, $validated);
    }

    protected function mergeImportSettings(array $settings, array $validated): array
    {
        $query = trim((string) ($validated['import_query'] ?? ''));

        if ($query === '') {
            unset($settings['import']);

            return $settings;
        }

        $settings['import'] = array_filter([
            'query' => $query,
            'page_size' => isset($validated['import_page_size']) ? (int) $validated['import_page_size'] : 20,
            'max_pages' => isset($validated['import_max_pages']) ? (int) $validated['import_max_pages'] : 1,
        ], fn ($value) => $value !== null && $value !== '');

        return $settings;
    }

    protected function buildProviderCredentials(ScanProvider $scanProvider, array $validated): array
    {
        return match ($scanProvider->provider_key) {
            'upcitemdb' => array_filter([
                'user_key' => trim((string) ($validated['upcitemdb_user_key'] ?? '')),
            ]),
            'barcode_lookup' => array_filter([
                'api_key' => trim((string) ($validated['barcode_lookup_api_key'] ?? '')),
            ]),
            'edamam' => array_filter([
                'app_id' => trim((string) ($validated['edamam_app_id'] ?? '')),
                'app_key' => trim((string) ($validated['edamam_app_key'] ?? '')),
            ]),
            'gs1_us' => array_filter([
                'api_key' => trim((string) ($validated['gs1_us_api_key'] ?? '')),
                'account_id' => trim((string) ($validated['gs1_us_account_id'] ?? '')),
            ], fn ($value) => $value !== ''),
            default => [],
        };
    }

    protected function sourceEnabled(ScanProvider $scanProvider, string $sourceKey, bool $default): bool
    {
        $source = collect($scanProvider->settings['sources'] ?? [])
            ->first(fn (array $item): bool => ($item['key'] ?? null) === $sourceKey);

        if (!is_array($source) || !array_key_exists('enabled', $source)) {
            return $default;
        }

        return filter_var($source['enabled'], FILTER_VALIDATE_BOOL);
    }
}
