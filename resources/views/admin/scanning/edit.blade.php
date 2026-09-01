<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <div class="text-sm text-slate-500">
                    <a href="{{ route('admin.scanning.providers.index') }}" class="hover:text-slate-700">Scan Operations</a>
                    <span class="mx-2">/</span>
                    <a href="{{ route('admin.scanning.providers.directory') }}" class="hover:text-slate-700">Provider Directory</a>
                    <span class="mx-2">/</span>
                    <span>{{ $scanProvider->name }}</span>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $scanProvider->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                        {{ $scanProvider->is_active ? 'Active' : 'Disabled' }}
                    </span>
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold
                        {{ $scanProvider->health_status === 'healthy' ? 'bg-emerald-50 text-emerald-700' : '' }}
                        {{ $scanProvider->health_status === 'degraded' ? 'bg-amber-100 text-amber-700' : '' }}
                        {{ $scanProvider->health_status === 'unknown' ? 'bg-slate-100 text-slate-600' : '' }}">
                        {{ ucfirst($scanProvider->health_status) }}
                    </span>
                    <span class="inline-flex items-center rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700">
                        {{ $scanProvider->provider_key }}
                    </span>
                </div>
                <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950">{{ $scanProvider->name }} Setup</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">Tune routing, credentials, import behavior, and operational thresholds for this provider. The page is structured around how teams actually manage integrations: identity first, runtime behavior second, secrets third.</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('admin.scanning.providers.directory') }}" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 transition">
                    Back to Directory
                </a>
                <form method="POST" action="{{ route('admin.scanning.providers.toggle-active', $scanProvider) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center rounded-xl border px-4 py-2.5 text-sm font-medium transition {{ $scanProvider->is_active ? 'border-red-200 bg-white text-red-700 hover:bg-red-50' : 'border-emerald-200 bg-white text-emerald-700 hover:bg-emerald-50' }}">
                        {{ $scanProvider->is_active ? 'Disable Provider' : 'Enable Provider' }}
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <section class="grid grid-cols-1 gap-4 lg:grid-cols-2 2xl:grid-cols-4">
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Success Rate</div>
                            <div class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ $scanProvider->quality_metrics['success_rate'] !== null ? $scanProvider->quality_metrics['success_rate'] . '%' : 'N/A' }}</div>
                            <div class="mt-1 text-sm text-slate-500">7-day provider resolution rate</div>
                        </div>
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a8 8 0 1 0 8 8 8 8 0 0 0-8-8Zm3.78 6.22a.75.75 0 0 0-1.06-1.06L9 10.88 7.28 9.16a.75.75 0 1 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.06 0l4.25-4.25Z"/></svg>
                        </span>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Median Latency</div>
                            <div class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ $scanProvider->quality_metrics['median_latency_ms'] !== null ? $scanProvider->quality_metrics['median_latency_ms'] . ' ms' : 'N/A' }}</div>
                            <div class="mt-1 text-sm text-slate-500">Typical turnaround over the last 7 days</div>
                        </div>
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-sky-50 text-sky-600">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 3a7 7 0 1 0 0 14 7 7 0 0 0 0-14Zm1 3a1 1 0 1 0-2 0v3a1 1 0 0 0 .293.707l2 2a1 1 0 1 0 1.414-1.414L11 8.586V6Z"/></svg>
                        </span>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Coverage</div>
                            <div class="mt-3 text-lg font-semibold tracking-tight text-slate-950">
                                {{ $scanProvider->quality_metrics['ingredient_coverage'] !== null ? $scanProvider->quality_metrics['ingredient_coverage'] . '%' : 'N/A' }}
                                <span class="mx-1 text-slate-300">/</span>
                                {{ $scanProvider->quality_metrics['nutrition_coverage'] !== null ? $scanProvider->quality_metrics['nutrition_coverage'] . '%' : 'N/A' }}
                            </div>
                            <div class="mt-1 text-sm text-slate-500">Ingredients vs nutrition coverage</div>
                        </div>
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-violet-50 text-violet-600">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M3 5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v3H3V5Zm0 5h14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-5Zm4 2a1 1 0 0 0 0 2h2a1 1 0 1 0 0-2H7Z"/></svg>
                        </span>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Recent Volume</div>
                            <div class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ $scanProvider->quality_metrics['attempts'] ?? 0 }}</div>
                            <div class="mt-1 text-sm text-slate-500">Provider attempts in the last 7 days</div>
                        </div>
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-amber-50 text-amber-600">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a1 1 0 0 1 1 1v1.07A7.002 7.002 0 0 1 17 11a7 7 0 1 1-8-6.93V3a1 1 0 0 1 1-1Zm1 5a1 1 0 1 0-2 0v4a1 1 0 0 0 .293.707l2 2a1 1 0 0 0 1.414-1.414L11 10.586V7Z"/></svg>
                        </span>
                    </div>
                </div>
            </section>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.4fr_0.8fr]">
                <section class="rounded-[32px] border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-slate-200 bg-[linear-gradient(135deg,#f8fafc_0%,#ffffff_55%,#eef6ff_100%)] px-6 py-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Configuration Studio</div>
                                <h3 class="mt-1 text-xl font-semibold text-slate-950">Provider Setup Form</h3>
                                <p class="mt-1 text-sm text-slate-500">Structured into logical sections so setup feels more like managing a product integration and less like editing a raw config blob.</p>
                            </div>
                            <div class="text-xs text-slate-500">
                                Driver
                                <div class="mt-1 rounded-full bg-white px-3 py-1.5 font-mono text-[11px] text-slate-700 shadow-sm">{{ $scanProvider->driver }}</div>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.scanning.providers.update', $scanProvider) }}" class="space-y-8 p-6">
                        @csrf

                        <section class="space-y-4">
                            <div>
                                <h4 class="text-base font-semibold text-slate-900">Identity & Routing</h4>
                                <p class="mt-1 text-sm text-slate-500">Control how this provider is labeled, prioritized, and scoped across product families.</p>
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Display Name</label>
                                    <input type="text" name="name" value="{{ old('name', $scanProvider->name) }}" class="w-full rounded-xl border-slate-300 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]" required>
                                </div>
                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Supported Families</label>
                                    <input type="text" name="supported_families" value="{{ old('supported_families', implode(', ', $scanProvider->supported_families ?? [])) }}" class="w-full rounded-xl border-slate-300 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]" placeholder="food, cosmetic, general">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Priority</label>
                                    <input type="number" name="priority" value="{{ old('priority', $scanProvider->priority) }}" class="w-full rounded-xl border-slate-300 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]" min="1" max="9999" required>
                                    <p class="mt-2 text-xs text-slate-500">Lower numbers are attempted earlier.</p>
                                </div>
                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Timeout (seconds)</label>
                                    <input type="number" name="timeout_seconds" value="{{ old('timeout_seconds', $scanProvider->timeout_seconds) }}" class="w-full rounded-xl border-slate-300 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]" min="1" max="60" required>
                                    <p class="mt-2 text-xs text-slate-500">Hard stop for each provider request.</p>
                                </div>
                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Retry Attempts</label>
                                    <input type="number" name="retry_attempts" value="{{ old('retry_attempts', $scanProvider->retry_attempts) }}" class="w-full rounded-xl border-slate-300 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]" min="0" max="10" required>
                                    <p class="mt-2 text-xs text-slate-500">Use sparingly to avoid cascading latency.</p>
                                </div>
                            </div>
                        </section>

                        <section class="space-y-4">
                            <div>
                                <h4 class="text-base font-semibold text-slate-900">Caching & Throughput</h4>
                                <p class="mt-1 text-sm text-slate-500">Manage how often this provider is hit and how aggressively the integration leans on cached responses.</p>
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Cache TTL (minutes)</label>
                                    <input type="number" name="cache_ttl_minutes" value="{{ old('cache_ttl_minutes', $scanProvider->cache_ttl_minutes) }}" class="w-full rounded-xl border-slate-300 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]" min="0" max="43200" required>
                                    <p class="mt-2 text-xs text-slate-500">Longer cache reduces cost and traffic, shorter cache improves freshness.</p>
                                </div>

                                @if($scanProvider->supports_import)
                                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4">
                                        <div class="flex items-center justify-between gap-3">
                                            <div>
                                                <label class="block text-sm font-medium text-emerald-900 mb-2">Provider Sync</label>
                                                <p class="text-xs text-emerald-700">Run a structured catalog import when query settings are present.</p>
                                            </div>
                                            <button
                                                form="provider-sync-form"
                                                type="submit"
                                                class="inline-flex items-center rounded-xl border border-emerald-300 bg-white px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                                @disabled(!$scanProvider->import_config_summary)
                                            >
                                                Sync Products
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </section>

                        @if($scanProvider->supports_import)
                            <section class="space-y-4">
                                <div>
                                    <h4 class="text-base font-semibold text-slate-900">Import Settings</h4>
                                    <p class="mt-1 text-sm text-slate-500">These power batch enrichment and one-click syncs from the provider catalog.</p>
                                </div>

                                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 lg:col-span-3">
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Import Query</label>
                                        <input type="text" name="import_query" value="{{ old('import_query', data_get($scanProvider->settings, 'import.query')) }}" class="w-full rounded-xl border-slate-300 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]" placeholder="snacks, beverages, cereal">
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Import Page Size</label>
                                        <input type="number" name="import_page_size" value="{{ old('import_page_size', data_get($scanProvider->settings, 'import.page_size', 20)) }}" class="w-full rounded-xl border-slate-300 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]" min="1" max="100">
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Import Max Pages</label>
                                        <input type="number" name="import_max_pages" value="{{ old('import_max_pages', data_get($scanProvider->settings, 'import.max_pages', 1)) }}" class="w-full rounded-xl border-slate-300 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]" min="1" max="20">
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                        <div class="text-sm font-medium text-slate-700">Current Import State</div>
                                        <div class="mt-3 text-sm text-slate-600">
                                            @if($scanProvider->import_config_summary)
                                                Query <span class="font-medium text-slate-900">{{ $scanProvider->import_config_summary['query'] }}</span>, up to {{ $scanProvider->import_config_summary['max_pages'] }} page(s) at {{ $scanProvider->import_config_summary['page_size'] }} rows each.
                                            @else
                                                No sync query configured yet.
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </section>
                        @endif

                        @if($scanProvider->provider_key === 'open_facts')
                            <section class="space-y-4">
                                <div>
                                    <h4 class="text-base font-semibold text-slate-900">Endpoint Configuration</h4>
                                    <p class="mt-1 text-sm text-slate-500">Control the source URLs for each Open Facts family branch.</p>
                                </div>

                                <div class="grid grid-cols-1 gap-4">
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Open Food Facts URL</label>
                                        <input type="url" name="open_food_facts_base_url" value="{{ old('open_food_facts_base_url', data_get($scanProvider->settings, 'sources.0.base_url', 'https://world.openfoodfacts.org/api/v2')) }}" class="w-full rounded-xl border-slate-300 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Open Beauty Facts URL</label>
                                        <input type="url" name="open_beauty_facts_base_url" value="{{ old('open_beauty_facts_base_url', data_get($scanProvider->settings, 'sources.1.base_url', 'https://world.openbeautyfacts.org/api/v2')) }}" class="w-full rounded-xl border-slate-300 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Open Product Facts URL</label>
                                        <input type="url" name="open_product_facts_base_url" value="{{ old('open_product_facts_base_url', data_get($scanProvider->settings, 'sources.2.base_url', 'https://world.openproductfacts.org/api/v2')) }}" class="w-full rounded-xl border-slate-300 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Open Pet Food Facts URL</label>
                                        <input type="url" name="open_pet_food_facts_base_url" value="{{ old('open_pet_food_facts_base_url', data_get($scanProvider->settings, 'sources.3.base_url', 'https://world.openpetfoodfacts.org/api/v2')) }}" class="w-full rounded-xl border-slate-300 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>
                                </div>
                            </section>
                        @elseif($scanProvider->provider_key === 'openai_vision')
                            <section class="space-y-4">
                                <div>
                                    <h4 class="text-base font-semibold text-slate-900">OpenAI Runtime</h4>
                                    <p class="mt-1 text-sm text-slate-500">Configure the OpenAI key and model settings used for image identity, ingredient extraction, nutrition extraction, and catalog image enrichment.</p>
                                </div>

                                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 lg:col-span-2">
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Base URL</label>
                                        <input type="url" name="openai_base_url" value="{{ old('openai_base_url', data_get($scanProvider->settings, 'base_url', 'https://api.openai.com/v1')) }}" class="w-full rounded-xl border-slate-300 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>
                                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4 lg:col-span-2">
                                        <label class="block text-sm font-medium text-emerald-900 mb-2">API Key</label>
                                        <input type="text" name="openai_api_key" value="{{ old('openai_api_key', data_get($scanProvider->credentials, 'api_key')) }}" class="w-full rounded-xl border-emerald-200 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Identity Model</label>
                                        <input type="text" name="openai_image_recognition_model" value="{{ old('openai_image_recognition_model', data_get($scanProvider->settings, 'image_recognition_model', 'gpt-5.4-mini')) }}" class="w-full rounded-xl border-slate-300 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Ingredient Model</label>
                                        <input type="text" name="openai_ingredient_extraction_model" value="{{ old('openai_ingredient_extraction_model', data_get($scanProvider->settings, 'ingredient_extraction_model', 'gpt-5.4-mini')) }}" class="w-full rounded-xl border-slate-300 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Nutrition Model</label>
                                        <input type="text" name="openai_nutrition_extraction_model" value="{{ old('openai_nutrition_extraction_model', data_get($scanProvider->settings, 'nutrition_extraction_model', 'gpt-5.4-mini')) }}" class="w-full rounded-xl border-slate-300 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>
                                </div>
                            </section>
                        @elseif($scanProvider->provider_key === 'google_cloud_vision')
                            <section class="space-y-4">
                                <div>
                                    <h4 class="text-base font-semibold text-slate-900">Google Cloud Vision Runtime</h4>
                                    <p class="mt-1 text-sm text-slate-500">Configure OCR credentials for photo and gallery scans. You can store a server file path or paste the service-account JSON directly.</p>
                                </div>

                                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Vision Base URL</label>
                                        <input type="url" name="google_cloud_vision_base_url" value="{{ old('google_cloud_vision_base_url', data_get($scanProvider->settings, 'base_url', 'https://vision.googleapis.com/v1')) }}" class="w-full rounded-xl border-slate-300 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Token URL</label>
                                        <input type="url" name="google_cloud_vision_token_url" value="{{ old('google_cloud_vision_token_url', data_get($scanProvider->settings, 'token_url', 'https://oauth2.googleapis.com/token')) }}" class="w-full rounded-xl border-slate-300 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>
                                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4 lg:col-span-2">
                                        <label class="block text-sm font-medium text-emerald-900 mb-2">Credentials Path</label>
                                        <input type="text" name="google_cloud_vision_credentials_path" value="{{ old('google_cloud_vision_credentials_path', data_get($scanProvider->credentials, 'credentials_path')) }}" class="w-full rounded-xl border-emerald-200 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]" placeholder="/path/to/service-account.json">
                                    </div>
                                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4 lg:col-span-2">
                                        <label class="block text-sm font-medium text-emerald-900 mb-2">Credentials JSON</label>
                                        <textarea name="google_cloud_vision_credentials_json" rows="8" class="w-full rounded-xl border-emerald-200 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]" placeholder='{"type":"service_account",...}'>{{ old('google_cloud_vision_credentials_json', data_get($scanProvider->credentials, 'credentials_json')) }}</textarea>
                                    </div>
                                </div>
                            </section>
                        @elseif($scanProvider->provider_key === 'edamam')
                            <section class="space-y-4">
                                <div>
                                    <h4 class="text-base font-semibold text-slate-900">API Access & Food Context</h4>
                                    <p class="mt-1 text-sm text-slate-500">This provider is food-only. Add credentials and tune the request context used for packaged-food lookups.</p>
                                </div>

                                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 lg:col-span-2">
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Base URL</label>
                                        <input type="url" name="edamam_base_url" value="{{ old('edamam_base_url', data_get($scanProvider->settings, 'base_url', 'https://api.edamam.com/api/food-database/v2/parser')) }}" class="w-full rounded-xl border-slate-300 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Category</label>
                                        <input type="text" name="edamam_category" value="{{ old('edamam_category', data_get($scanProvider->settings, 'category', 'packaged-foods')) }}" class="w-full rounded-xl border-slate-300 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Nutrition Type</label>
                                        <input type="text" name="edamam_nutrition_type" value="{{ old('edamam_nutrition_type', data_get($scanProvider->settings, 'nutrition_type', 'cooking')) }}" class="w-full rounded-xl border-slate-300 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>
                                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4">
                                        <label class="block text-sm font-medium text-emerald-900 mb-2">App ID</label>
                                        <input type="text" name="edamam_app_id" value="{{ old('edamam_app_id', data_get($scanProvider->credentials, 'app_id')) }}" class="w-full rounded-xl border-emerald-200 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>
                                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4">
                                        <label class="block text-sm font-medium text-emerald-900 mb-2">App Key</label>
                                        <input type="text" name="edamam_app_key" value="{{ old('edamam_app_key', data_get($scanProvider->credentials, 'app_key')) }}" class="w-full rounded-xl border-emerald-200 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>
                                </div>
                            </section>
                        @elseif($scanProvider->provider_key === 'usda_fdc')
                            <section class="space-y-4">
                                <div>
                                    <h4 class="text-base font-semibold text-slate-900">Search Endpoint & API Key</h4>
                                    <p class="mt-1 text-sm text-slate-500">USDA is used as a branded-food enrichment and import source. It stays food-only and search-based, so it does not replace the primary barcode-verification flow.</p>
                                </div>

                                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 lg:col-span-2">
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Search Endpoint URL</label>
                                        <input type="url" name="usda_fdc_base_url" value="{{ old('usda_fdc_base_url', data_get($scanProvider->settings, 'base_url', 'https://api.nal.usda.gov/fdc/v1/foods/search')) }}" class="w-full rounded-xl border-slate-300 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Data Types</label>
                                        <input type="text" name="usda_fdc_data_types" value="{{ old('usda_fdc_data_types', implode(', ', (array) data_get($scanProvider->settings, 'data_types', ['Branded']))) }}" class="w-full rounded-xl border-slate-300 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]" placeholder="Branded">
                                        <p class="mt-2 text-xs text-slate-500">Comma-separated. `Branded` is the recommended default for packaged products.</p>
                                    </div>
                                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4">
                                        <label class="block text-sm font-medium text-emerald-900 mb-2">API Key</label>
                                        <input type="text" name="usda_fdc_api_key" value="{{ old('usda_fdc_api_key', data_get($scanProvider->credentials, 'api_key')) }}" class="w-full rounded-xl border-emerald-200 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>
                                </div>
                            </section>
                        @elseif($scanProvider->provider_key === 'gs1_us')
                            <section class="space-y-4">
                                <div>
                                    <h4 class="text-base font-semibold text-slate-900">Verification Endpoint & Credentials</h4>
                                    <p class="mt-1 text-sm text-slate-500">Configure the exact GS1 endpoint contract used by your subscription.</p>
                                </div>

                                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 lg:col-span-3">
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Base URL</label>
                                        <input type="url" name="gs1_us_base_url" value="{{ old('gs1_us_base_url', data_get($scanProvider->settings, 'base_url')) }}" class="w-full rounded-xl border-slate-300 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                        <label class="block text-sm font-medium text-slate-700 mb-2">HTTP Method</label>
                                        <select name="gs1_us_http_method" class="w-full rounded-xl border-slate-300 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]">
                                            <option value="GET" @selected(old('gs1_us_http_method', data_get($scanProvider->settings, 'http_method', 'GET')) === 'GET')>GET</option>
                                            <option value="POST" @selected(old('gs1_us_http_method', data_get($scanProvider->settings, 'http_method', 'GET')) === 'POST')>POST</option>
                                        </select>
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Barcode Field</label>
                                        <input type="text" name="gs1_us_barcode_field" value="{{ old('gs1_us_barcode_field', data_get($scanProvider->settings, 'barcode_field', 'gtin')) }}" class="w-full rounded-xl border-slate-300 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Barcode Path</label>
                                        <input type="text" name="gs1_us_barcode_path" value="{{ old('gs1_us_barcode_path', data_get($scanProvider->settings, 'barcode_path', 'gtin')) }}" class="w-full rounded-xl border-slate-300 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>
                                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4 lg:col-span-2">
                                        <label class="block text-sm font-medium text-emerald-900 mb-2">API Key</label>
                                        <input type="text" name="gs1_us_api_key" value="{{ old('gs1_us_api_key', data_get($scanProvider->credentials, 'api_key')) }}" class="w-full rounded-xl border-emerald-200 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Account ID</label>
                                        <input type="text" name="gs1_us_account_id" value="{{ old('gs1_us_account_id', data_get($scanProvider->credentials, 'account_id')) }}" class="w-full rounded-xl border-slate-300 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]" placeholder="Optional">
                                    </div>
                                </div>
                            </section>
                        @endif

                        <section class="space-y-4">
                            <div>
                                <h4 class="text-base font-semibold text-slate-900">Operator Notes</h4>
                                <p class="mt-1 text-sm text-slate-500">Leave intent, caveats, or runbook context for the next person touching this integration.</p>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                <label class="block text-sm font-medium text-slate-700 mb-2">Notes</label>
                                <textarea name="notes" rows="4" class="w-full rounded-xl border-slate-300 bg-white focus:border-[#1FA774] focus:ring-[#1FA774]">{{ old('notes', $scanProvider->notes) }}</textarea>
                            </div>
                        </section>

                        <div class="flex flex-col gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
                            <div class="text-sm text-slate-500">
                                Changes save directly to the provider runtime configuration.
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <a href="{{ route('admin.scanning.providers.directory') }}" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                                    Cancel
                                </a>
                                <button type="submit" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-medium text-slate-900 shadow-sm hover:bg-slate-50 transition">
                                    Save Provider Setup
                                </button>
                            </div>
                        </div>
                    </form>
                </section>

                <aside class="space-y-6">
                    <section class="rounded-[32px] border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <div class="border-b border-slate-200 px-5 py-4">
                            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Snapshot</div>
                            <h3 class="mt-1 text-lg font-semibold text-slate-950">Operational Summary</h3>
                        </div>
                        <div class="space-y-4 px-5 py-5">
                            <div class="rounded-2xl bg-slate-50 p-4">
                                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Recent Attempts</div>
                                <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $scanProvider->quality_metrics['attempts'] ?? 0 }}</div>
                            </div>
                            <div class="rounded-2xl bg-slate-50 p-4">
                                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Average Confidence</div>
                                <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $scanProvider->quality_metrics['average_confidence'] ?? 'N/A' }}</div>
                            </div>
                            <div class="rounded-2xl bg-slate-50 p-4">
                                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Families</div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @forelse($scanProvider->supported_families ?? [] as $family)
                                        <span class="inline-flex items-center rounded-full bg-white px-2.5 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-200">
                                            {{ str_replace('_', ' ', $family) }}
                                        </span>
                                    @empty
                                        <span class="text-sm text-slate-500">All families</span>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[32px] border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <div class="border-b border-slate-200 px-5 py-4">
                            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Provider Activity</div>
                            <h3 class="mt-1 text-lg font-semibold text-slate-950">Recent Attempts</h3>
                            <p class="mt-1 text-sm text-slate-500">Latest 20 scan attempts tied to this provider.</p>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50/80">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-medium uppercase tracking-wider text-slate-500">Barcode</th>
                                        <th class="px-4 py-3 text-left font-medium uppercase tracking-wider text-slate-500">Status</th>
                                        <th class="px-4 py-3 text-left font-medium uppercase tracking-wider text-slate-500">Latency</th>
                                        <th class="px-4 py-3 text-left font-medium uppercase tracking-wider text-slate-500">Time</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @forelse($recentLookups as $lookup)
                                        <tr class="hover:bg-slate-50/60 transition">
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
                                            <td colspan="4" class="px-4 py-8 text-center text-slate-500">No provider attempts recorded yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>
                </aside>
            </div>
        </div>
    </div>

    @if($scanProvider->supports_import)
        <form id="provider-sync-form" method="POST" action="{{ route('admin.scanning.providers.sync-products', $scanProvider) }}" class="hidden">
            @csrf
        </form>
    @endif
</x-app-layout>
