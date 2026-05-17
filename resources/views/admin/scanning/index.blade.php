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

                        <form method="POST" action="{{ route('admin.scanning.providers.update', $provider) }}" class="space-y-4">
                            @csrf
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

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Settings JSON</label>
                                <textarea name="settings_json" rows="7" class="w-full rounded-lg border-gray-300 font-mono text-xs focus:border-[#1FA774] focus:ring-[#1FA774]">{{ old('settings_json', json_encode($provider->settings ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) }}</textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Credentials JSON</label>
                                <textarea name="credentials_json" rows="5" class="w-full rounded-lg border-gray-300 font-mono text-xs focus:border-[#1FA774] focus:ring-[#1FA774]">{{ old('credentials_json', json_encode($provider->credentials ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) }}</textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                                <textarea name="notes" rows="3" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">{{ old('notes', $provider->notes) }}</textarea>
                            </div>

                            <div class="flex items-center justify-between gap-3">
                                <button type="submit" class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-700 transition">Save Settings</button>
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
