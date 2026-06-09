<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800">Scanning Providers</h2>
                <p class="text-sm text-gray-500 mt-1">Control which providers are active before Scanwell returns a product-not-found response.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                @foreach($providers as $provider)
                    <div class="bg-white rounded-xl shadow-sm p-6 space-y-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">{{ $provider->name }}</h3>
                                <p class="text-sm text-gray-500 mt-1">{{ $provider->provider_key }}</p>
                            </div>
                            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $provider->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                {{ $provider->is_active ? 'Active' : 'Disabled' }}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-lg bg-slate-50 p-3">
                                <div class="text-slate-500">Priority</div>
                                <div class="font-semibold text-slate-900">{{ $provider->priority }}</div>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-3">
                                <div class="text-slate-500">Health</div>
                                <div class="font-semibold text-slate-900">{{ ucfirst($provider->health_status) }}</div>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-3">
                                <div class="text-slate-500">Recent Lookups</div>
                                <div class="font-semibold text-slate-900">{{ $provider->recent_lookups_count }}</div>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-3">
                                <div class="text-slate-500">Recent Successes</div>
                                <div class="font-semibold text-slate-900">{{ $provider->recent_successful_lookups_count }}</div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-white p-4 space-y-3">
                            <div>
                                <h4 class="text-sm font-semibold text-slate-900">7-Day Quality Snapshot</h4>
                                <p class="mt-1 text-xs text-slate-500">Use this to compare launch-readiness across providers.</p>
                            </div>

                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div class="rounded-lg bg-slate-50 p-3">
                                    <div class="text-slate-500">Success Rate</div>
                                    <div class="font-semibold text-slate-900">{{ $provider->quality_metrics['success_rate'] !== null ? $provider->quality_metrics['success_rate'] . '%' : 'N/A' }}</div>
                                </div>
                                <div class="rounded-lg bg-slate-50 p-3">
                                    <div class="text-slate-500">Match Rate</div>
                                    <div class="font-semibold text-slate-900">{{ $provider->quality_metrics['match_rate'] !== null ? $provider->quality_metrics['match_rate'] . '%' : 'N/A' }}</div>
                                </div>
                                <div class="rounded-lg bg-slate-50 p-3">
                                    <div class="text-slate-500">Error Rate</div>
                                    <div class="font-semibold text-slate-900">{{ $provider->quality_metrics['error_rate'] !== null ? $provider->quality_metrics['error_rate'] . '%' : 'N/A' }}</div>
                                </div>
                                <div class="rounded-lg bg-slate-50 p-3">
                                    <div class="text-slate-500">Median Latency</div>
                                    <div class="font-semibold text-slate-900">{{ $provider->quality_metrics['median_latency_ms'] !== null ? $provider->quality_metrics['median_latency_ms'] . ' ms' : 'N/A' }}</div>
                                </div>
                                <div class="rounded-lg bg-slate-50 p-3">
                                    <div class="text-slate-500">Ingredient Coverage</div>
                                    <div class="font-semibold text-slate-900">{{ $provider->quality_metrics['ingredient_coverage'] !== null ? $provider->quality_metrics['ingredient_coverage'] . '%' : 'N/A' }}</div>
                                </div>
                                <div class="rounded-lg bg-slate-50 p-3">
                                    <div class="text-slate-500">Nutrition Coverage</div>
                                    <div class="font-semibold text-slate-900">{{ $provider->quality_metrics['nutrition_coverage'] !== null ? $provider->quality_metrics['nutrition_coverage'] . '%' : 'N/A' }}</div>
                                </div>
                                <div class="rounded-lg bg-slate-50 p-3">
                                    <div class="text-slate-500">Avg Confidence</div>
                                    <div class="font-semibold text-slate-900">{{ $provider->quality_metrics['average_confidence'] !== null ? $provider->quality_metrics['average_confidence'] : 'N/A' }}</div>
                                </div>
                                <div class="rounded-lg bg-slate-50 p-3">
                                    <div class="text-slate-500">Avg Completeness</div>
                                    <div class="font-semibold text-slate-900">{{ $provider->quality_metrics['average_completeness'] !== null ? $provider->quality_metrics['average_completeness'] : 'N/A' }}</div>
                                </div>
                            </div>

                            <div>
                                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Recent Family Mix</div>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @forelse($provider->quality_metrics['family_breakdown'] as $family => $count)
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                                            {{ str_replace('_', ' ', ucfirst($family)) }}: {{ $count }}
                                        </span>
                                    @empty
                                        <span class="text-xs text-slate-500">No recent provider attempts yet.</span>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-dashed border-slate-200 bg-slate-50 p-3 text-sm">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="font-medium text-slate-800">Product Sync</div>
                                    @if($provider->supports_import)
                                        <div class="mt-1 text-slate-600">
                                            @if($provider->import_config_summary)
                                                Query: <span class="font-medium text-slate-800">{{ $provider->import_config_summary['query'] }}</span>
                                            @else
                                                Configure <span class="font-mono text-xs">settings.import.query</span> below to enable one-click imports.
                                            @endif
                                        </div>
                                    @else
                                        <div class="mt-1 text-slate-500">This provider currently supports barcode lookup only.</div>
                                    @endif
                                </div>
                                @if($provider->supports_import)
                                    <form method="POST" action="{{ route('admin.scanning.providers.sync-products', $provider) }}">
                                        @csrf
                                        <button
                                            type="submit"
                                            class="px-4 py-2 rounded-lg border border-emerald-200 text-emerald-700 hover:bg-emerald-50 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                            @disabled(!$provider->import_config_summary)
                                        >
                                            Sync Products
                                        </button>
                                    </form>
                                @endif
                            </div>
                            @if($provider->supports_import && $provider->import_config_summary)
                                <div class="mt-2 text-xs text-slate-500">
                                    Runs {{ $provider->import_config_summary['max_pages'] }} page(s) at up to {{ $provider->import_config_summary['page_size'] }} products per page.
                                </div>
                            @endif
                        </div>

                        <form method="POST" action="{{ route('admin.scanning.providers.update', $provider) }}" class="space-y-4">
                            @csrf
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-semibold text-slate-900">Provider Configuration</div>
                                        <div class="text-xs text-slate-500">Make changes and save this provider.</div>
                                    </div>
                                    <button type="submit" class="shrink-0 px-4 py-2 rounded-lg border border-slate-300 bg-white text-black hover:bg-slate-100 hover:border-slate-400 transition">
                                        Save Settings
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Display Name</label>
                                <input type="text" name="name" value="{{ old('name', $provider->name) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]" required>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                                    <input type="number" name="priority" value="{{ old('priority', $provider->priority) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]" min="1" max="9999" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Families</label>
                                    <input type="text" name="supported_families" value="{{ old('supported_families', implode(', ', $provider->supported_families ?? [])) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]" placeholder="food, cosmetic, general">
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Timeout</label>
                                    <input type="number" name="timeout_seconds" value="{{ old('timeout_seconds', $provider->timeout_seconds) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]" min="1" max="60" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Retries</label>
                                    <input type="number" name="retry_attempts" value="{{ old('retry_attempts', $provider->retry_attempts) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]" min="0" max="10" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Cache TTL</label>
                                    <input type="number" name="cache_ttl_minutes" value="{{ old('cache_ttl_minutes', $provider->cache_ttl_minutes) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]" min="0" max="43200" required>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Driver</label>
                                <input type="text" value="{{ $provider->driver }}" class="w-full rounded-lg border-gray-200 bg-slate-50 text-slate-500" disabled>
                            </div>

                            @if($provider->supports_import)
                                <div class="rounded-lg border border-emerald-100 bg-emerald-50/40 p-4 space-y-4">
                                    <div>
                                        <h4 class="text-sm font-semibold text-emerald-900">Import Settings</h4>
                                        <p class="mt-1 text-xs text-emerald-700">These fields are saved into <span class="font-mono">settings.import</span> automatically.</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Import Query</label>
                                        <input
                                            type="text"
                                            name="import_query"
                                            value="{{ old('import_query', data_get($provider->settings, 'import.query')) }}"
                                            class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]"
                                            placeholder="snacks, beverages, cereal"
                                        >
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Import Page Size</label>
                                            <input
                                                type="number"
                                                name="import_page_size"
                                                value="{{ old('import_page_size', data_get($provider->settings, 'import.page_size', 20)) }}"
                                                class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]"
                                                min="1"
                                                max="100"
                                            >
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Import Max Pages</label>
                                            <input
                                                type="number"
                                                name="import_max_pages"
                                                value="{{ old('import_max_pages', data_get($provider->settings, 'import.max_pages', 1)) }}"
                                                class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]"
                                                min="1"
                                                max="20"
                                            >
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if($provider->provider_key === 'open_facts')
                                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 space-y-4">
                                    <div>
                                        <h4 class="text-sm font-semibold text-slate-900">Provider Settings</h4>
                                        <p class="mt-1 text-xs text-slate-600">Configure the Open Facts family endpoints used for scans and imports.</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Open Food Facts URL</label>
                                        <input type="url" name="open_food_facts_base_url" value="{{ old('open_food_facts_base_url', data_get($provider->settings, 'sources.0.base_url', 'https://world.openfoodfacts.org/api/v2')) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Open Beauty Facts URL</label>
                                        <input type="url" name="open_beauty_facts_base_url" value="{{ old('open_beauty_facts_base_url', data_get($provider->settings, 'sources.1.base_url', 'https://world.openbeautyfacts.org/api/v2')) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Open Product Facts URL</label>
                                        <input type="url" name="open_product_facts_base_url" value="{{ old('open_product_facts_base_url', data_get($provider->settings, 'sources.2.base_url', 'https://world.openproductfacts.org/api/v2')) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Open Pet Food Facts URL</label>
                                        <input type="url" name="open_pet_food_facts_base_url" value="{{ old('open_pet_food_facts_base_url', data_get($provider->settings, 'sources.3.base_url', 'https://world.openpetfoodfacts.org/api/v2')) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>
                                </div>
                            @elseif($provider->provider_key === 'barcode_lookup')
                                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 space-y-4">
                                    <div>
                                        <h4 class="text-sm font-semibold text-slate-900">Provider Settings</h4>
                                        <p class="mt-1 text-xs text-slate-600">Configure the Barcode Lookup endpoint and API key.</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Base URL</label>
                                        <input type="url" name="barcode_lookup_base_url" value="{{ old('barcode_lookup_base_url', data_get($provider->settings, 'base_url', 'https://api.barcodelookup.com/v3/products')) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">API Key</label>
                                        <input type="text" name="barcode_lookup_api_key" value="{{ old('barcode_lookup_api_key', data_get($provider->credentials, 'api_key')) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>
                                </div>
                            @elseif($provider->provider_key === 'edamam')
                                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 space-y-4">
                                    <div>
                                        <h4 class="text-sm font-semibold text-slate-900">Provider Settings</h4>
                                        <p class="mt-1 text-xs text-slate-600">Food-only enrichment. Add both the App ID and App Key to activate it.</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Base URL</label>
                                        <input type="url" name="edamam_base_url" value="{{ old('edamam_base_url', data_get($provider->settings, 'base_url', 'https://api.edamam.com/api/food-database/v2/parser')) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                                            <input type="text" name="edamam_category" value="{{ old('edamam_category', data_get($provider->settings, 'category', 'packaged-foods')) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Nutrition Type</label>
                                            <input type="text" name="edamam_nutrition_type" value="{{ old('edamam_nutrition_type', data_get($provider->settings, 'nutrition_type', 'cooking')) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">App ID</label>
                                            <input type="text" name="edamam_app_id" value="{{ old('edamam_app_id', data_get($provider->credentials, 'app_id')) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">App Key</label>
                                            <input type="text" name="edamam_app_key" value="{{ old('edamam_app_key', data_get($provider->credentials, 'app_key')) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                        </div>
                                    </div>
                                </div>
                            @elseif($provider->provider_key === 'gs1_us')
                                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 space-y-4">
                                    <div>
                                        <h4 class="text-sm font-semibold text-slate-900">Provider Settings</h4>
                                        <p class="mt-1 text-xs text-slate-600">Use the exact endpoint and request mapping from your GS1 US developer portal setup.</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Base URL</label>
                                        <input type="url" name="gs1_us_base_url" value="{{ old('gs1_us_base_url', data_get($provider->settings, 'base_url')) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>

                                    <div class="grid grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">HTTP Method</label>
                                            <select name="gs1_us_http_method" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                                <option value="GET" @selected(old('gs1_us_http_method', data_get($provider->settings, 'http_method', 'GET')) === 'GET')>GET</option>
                                                <option value="POST" @selected(old('gs1_us_http_method', data_get($provider->settings, 'http_method', 'GET')) === 'POST')>POST</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Barcode Field</label>
                                            <input type="text" name="gs1_us_barcode_field" value="{{ old('gs1_us_barcode_field', data_get($provider->settings, 'barcode_field', 'gtin')) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Barcode Path</label>
                                            <input type="text" name="gs1_us_barcode_path" value="{{ old('gs1_us_barcode_path', data_get($provider->settings, 'barcode_path', 'gtin')) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">API Key</label>
                                            <input type="text" name="gs1_us_api_key" value="{{ old('gs1_us_api_key', data_get($provider->credentials, 'api_key')) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Account ID</label>
                                            <input type="text" name="gs1_us_account_id" value="{{ old('gs1_us_account_id', data_get($provider->credentials, 'account_id')) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]" placeholder="Optional">
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                                <textarea name="notes" rows="3" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">{{ old('notes', $provider->notes) }}</textarea>
                            </div>

                            <div class="flex items-center justify-between gap-3">
                                <button type="submit" class="px-4 py-2 rounded-lg border border-slate-300 bg-white text-black hover:bg-slate-100 hover:border-slate-400 transition">Save Settings</button>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('admin.scanning.providers.toggle-active', $provider) }}">
                            @csrf
                            <button type="submit" class="w-full px-4 py-2 rounded-lg border transition {{ $provider->is_active ? 'border-red-200 text-red-700 hover:bg-red-50' : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50' }}">
                                {{ $provider->is_active ? 'Disable Provider' : 'Enable Provider' }}
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Recent Provider Attempts</h3>
                    <p class="text-sm text-gray-500">Latest 20 provider lookups across barcode scans and barcode-triggered searches.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Provider</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Barcode</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Latency</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Confidence</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse($recentLookups as $lookup)
                                <tr>
                                    <td class="px-4 py-3 text-gray-900">{{ $lookup->provider?->name ?? 'Unknown' }}</td>
                                    <td class="px-4 py-3 font-mono text-gray-700">{{ $lookup->barcode }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold
                                            {{ $lookup->status === 'success' ? 'bg-emerald-100 text-emerald-700' : '' }}
                                            {{ $lookup->status === 'error' ? 'bg-red-100 text-red-700' : '' }}
                                            {{ in_array($lookup->status, ['not_found', 'rejected'], true) ? 'bg-amber-100 text-amber-700' : '' }}">
                                            {{ str_replace('_', ' ', ucfirst($lookup->status)) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">{{ $lookup->latency_ms ? $lookup->latency_ms . ' ms' : 'N/A' }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $lookup->confidence ?? 'N/A' }}</td>
                                    <td class="px-4 py-3 text-gray-500">{{ $lookup->created_at?->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-gray-500">No provider attempts recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
