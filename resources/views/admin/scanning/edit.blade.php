<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <div class="text-sm text-slate-500">
                    <a href="{{ route('admin.scanning.providers.index') }}" class="hover:text-slate-700">Scanning Providers</a>
                    <span class="mx-2">/</span>
                    <span>{{ $scanProvider->name }}</span>
                </div>
                <h2 class="mt-1 font-semibold text-xl text-gray-800">Provider Setup</h2>
                <p class="mt-1 text-sm text-gray-500">Configuration, credentials, and recent activity for {{ $scanProvider->provider_key }}.</p>
            </div>
            <a href="{{ route('admin.scanning.providers.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                Back to Dashboard
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Status</div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $scanProvider->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                            {{ $scanProvider->is_active ? 'Active' : 'Disabled' }}
                        </span>
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold
                            {{ $scanProvider->health_status === 'healthy' ? 'bg-emerald-50 text-emerald-700' : '' }}
                            {{ $scanProvider->health_status === 'degraded' ? 'bg-amber-100 text-amber-700' : '' }}
                            {{ $scanProvider->health_status === 'unknown' ? 'bg-slate-100 text-slate-600' : '' }}">
                            {{ ucfirst($scanProvider->health_status) }}
                        </span>
                    </div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">7d Success</div>
                    <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $scanProvider->quality_metrics['success_rate'] !== null ? $scanProvider->quality_metrics['success_rate'] . '%' : 'N/A' }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Median Latency</div>
                    <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $scanProvider->quality_metrics['median_latency_ms'] !== null ? $scanProvider->quality_metrics['median_latency_ms'] . ' ms' : 'N/A' }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Avg Completeness</div>
                    <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $scanProvider->quality_metrics['average_completeness'] ?? 'N/A' }}</div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <div class="xl:col-span-2 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-slate-200 px-6 py-4">
                        <h3 class="text-lg font-semibold text-slate-900">Provider Configuration</h3>
                        <p class="mt-1 text-sm text-slate-500">Update settings here without cluttering the main dashboard.</p>
                    </div>

                    <form method="POST" action="{{ route('admin.scanning.providers.update', $scanProvider) }}" class="space-y-6 p-6">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Display Name</label>
                                <input type="text" name="name" value="{{ old('name', $scanProvider->name) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Families</label>
                                <input type="text" name="supported_families" value="{{ old('supported_families', implode(', ', $scanProvider->supported_families ?? [])) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]" placeholder="food, cosmetic, general">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                                <input type="number" name="priority" value="{{ old('priority', $scanProvider->priority) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]" min="1" max="9999" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Timeout</label>
                                <input type="number" name="timeout_seconds" value="{{ old('timeout_seconds', $scanProvider->timeout_seconds) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]" min="1" max="60" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Retries</label>
                                <input type="number" name="retry_attempts" value="{{ old('retry_attempts', $scanProvider->retry_attempts) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]" min="0" max="10" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Cache TTL</label>
                                <input type="number" name="cache_ttl_minutes" value="{{ old('cache_ttl_minutes', $scanProvider->cache_ttl_minutes) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]" min="0" max="43200" required>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Driver</label>
                            <input type="text" value="{{ $scanProvider->driver }}" class="w-full rounded-lg border-gray-200 bg-slate-50 text-slate-500" disabled>
                        </div>

                        @if($scanProvider->supports_import)
                            <div class="rounded-xl border border-emerald-100 bg-emerald-50/40 p-4 space-y-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-emerald-900">Import Settings</h4>
                                    <p class="mt-1 text-xs text-emerald-700">These fields map to <span class="font-mono">settings.import</span>.</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Import Query</label>
                                    <input type="text" name="import_query" value="{{ old('import_query', data_get($scanProvider->settings, 'import.query')) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]" placeholder="snacks, beverages, cereal">
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Import Page Size</label>
                                        <input type="number" name="import_page_size" value="{{ old('import_page_size', data_get($scanProvider->settings, 'import.page_size', 20)) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]" min="1" max="100">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Import Max Pages</label>
                                        <input type="number" name="import_max_pages" value="{{ old('import_max_pages', data_get($scanProvider->settings, 'import.max_pages', 1)) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]" min="1" max="20">
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if($scanProvider->provider_key === 'open_facts')
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-slate-900">Provider Settings</h4>
                                    <p class="mt-1 text-xs text-slate-600">Configure the Open Facts family endpoints used for scans and imports.</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Open Food Facts URL</label>
                                    <input type="url" name="open_food_facts_base_url" value="{{ old('open_food_facts_base_url', data_get($scanProvider->settings, 'sources.0.base_url', 'https://world.openfoodfacts.org/api/v2')) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Open Beauty Facts URL</label>
                                    <input type="url" name="open_beauty_facts_base_url" value="{{ old('open_beauty_facts_base_url', data_get($scanProvider->settings, 'sources.1.base_url', 'https://world.openbeautyfacts.org/api/v2')) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Open Product Facts URL</label>
                                    <input type="url" name="open_product_facts_base_url" value="{{ old('open_product_facts_base_url', data_get($scanProvider->settings, 'sources.2.base_url', 'https://world.openproductfacts.org/api/v2')) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Open Pet Food Facts URL</label>
                                    <input type="url" name="open_pet_food_facts_base_url" value="{{ old('open_pet_food_facts_base_url', data_get($scanProvider->settings, 'sources.3.base_url', 'https://world.openpetfoodfacts.org/api/v2')) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                </div>
                            </div>
                        @elseif($scanProvider->provider_key === 'edamam')
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-slate-900">Provider Settings</h4>
                                    <p class="mt-1 text-xs text-slate-600">Food-only enrichment. Add both the App ID and App Key to activate it.</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Base URL</label>
                                    <input type="url" name="edamam_base_url" value="{{ old('edamam_base_url', data_get($scanProvider->settings, 'base_url', 'https://api.edamam.com/api/food-database/v2/parser')) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                                        <input type="text" name="edamam_category" value="{{ old('edamam_category', data_get($scanProvider->settings, 'category', 'packaged-foods')) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Nutrition Type</label>
                                        <input type="text" name="edamam_nutrition_type" value="{{ old('edamam_nutrition_type', data_get($scanProvider->settings, 'nutrition_type', 'cooking')) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">App ID</label>
                                        <input type="text" name="edamam_app_id" value="{{ old('edamam_app_id', data_get($scanProvider->credentials, 'app_id')) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">App Key</label>
                                        <input type="text" name="edamam_app_key" value="{{ old('edamam_app_key', data_get($scanProvider->credentials, 'app_key')) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>
                                </div>
                            </div>
                        @elseif($scanProvider->provider_key === 'gs1_us')
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-slate-900">Provider Settings</h4>
                                    <p class="mt-1 text-xs text-slate-600">Use the exact endpoint and request mapping from your GS1 US developer portal setup.</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Base URL</label>
                                    <input type="url" name="gs1_us_base_url" value="{{ old('gs1_us_base_url', data_get($scanProvider->settings, 'base_url')) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">HTTP Method</label>
                                        <select name="gs1_us_http_method" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                            <option value="GET" @selected(old('gs1_us_http_method', data_get($scanProvider->settings, 'http_method', 'GET')) === 'GET')>GET</option>
                                            <option value="POST" @selected(old('gs1_us_http_method', data_get($scanProvider->settings, 'http_method', 'GET')) === 'POST')>POST</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Barcode Field</label>
                                        <input type="text" name="gs1_us_barcode_field" value="{{ old('gs1_us_barcode_field', data_get($scanProvider->settings, 'barcode_field', 'gtin')) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Barcode Path</label>
                                        <input type="text" name="gs1_us_barcode_path" value="{{ old('gs1_us_barcode_path', data_get($scanProvider->settings, 'barcode_path', 'gtin')) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">API Key</label>
                                        <input type="text" name="gs1_us_api_key" value="{{ old('gs1_us_api_key', data_get($scanProvider->credentials, 'api_key')) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Account ID</label>
                                        <input type="text" name="gs1_us_account_id" value="{{ old('gs1_us_account_id', data_get($scanProvider->credentials, 'account_id')) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]" placeholder="Optional">
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                            <textarea name="notes" rows="3" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">{{ old('notes', $scanProvider->notes) }}</textarea>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <button type="submit" class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 transition">
                                Save Settings
                            </button>
                        </div>
                    </form>
                </div>

                <div class="space-y-6">
                    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-slate-900">Quick Actions</h3>
                        <p class="mt-1 text-sm text-slate-500">Fast controls for this provider.</p>

                        <div class="mt-4 space-y-3">
                            <form method="POST" action="{{ route('admin.scanning.providers.toggle-active', $scanProvider) }}">
                                @csrf
                                <button type="submit" class="w-full rounded-lg border px-4 py-2.5 text-sm font-medium transition {{ $scanProvider->is_active ? 'border-red-200 text-red-700 hover:bg-red-50' : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50' }}">
                                    {{ $scanProvider->is_active ? 'Disable Provider' : 'Enable Provider' }}
                                </button>
                            </form>
                            @if($scanProvider->supports_import)
                                <form method="POST" action="{{ route('admin.scanning.providers.sync-products', $scanProvider) }}">
                                    @csrf
                                    <button
                                        type="submit"
                                        class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                        @disabled(!$scanProvider->import_config_summary)
                                    >
                                        Sync Products
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <div class="border-b border-slate-200 px-6 py-4">
                            <h3 class="text-lg font-semibold text-slate-900">Recent Attempts</h3>
                            <p class="mt-1 text-sm text-slate-500">Latest 20 lookups for this provider.</p>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-medium uppercase tracking-wider text-slate-500">Barcode</th>
                                        <th class="px-4 py-3 text-left font-medium uppercase tracking-wider text-slate-500">Status</th>
                                        <th class="px-4 py-3 text-left font-medium uppercase tracking-wider text-slate-500">Latency</th>
                                        <th class="px-4 py-3 text-left font-medium uppercase tracking-wider text-slate-500">Time</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @forelse($recentLookups as $lookup)
                                        <tr>
                                            <td class="px-4 py-3 font-mono text-slate-700">{{ $lookup->barcode }}</td>
                                            <td class="px-4 py-3">
                                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold
                                                    {{ $lookup->status === 'success' ? 'bg-emerald-100 text-emerald-700' : '' }}
                                                    {{ $lookup->status === 'error' ? 'bg-red-100 text-red-700' : '' }}
                                                    {{ in_array($lookup->status, ['not_found', 'rejected'], true) ? 'bg-amber-100 text-amber-700' : '' }}">
                                                    {{ str_replace('_', ' ', ucfirst($lookup->status)) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-slate-700">{{ $lookup->latency_ms ? $lookup->latency_ms . ' ms' : 'N/A' }}</td>
                                            <td class="px-4 py-3 text-slate-500">{{ $lookup->created_at?->diffForHumans() }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-4 py-6 text-center text-slate-500">No provider attempts recorded yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
