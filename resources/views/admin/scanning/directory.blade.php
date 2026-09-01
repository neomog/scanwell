<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="text-sm text-slate-500">
                    <a href="{{ route('admin.scanning.providers.index') }}" class="hover:text-slate-700">Scan Operations</a>
                    <span class="mx-2">/</span>
                    <span>Provider Directory</span>
                </div>
                <h2 class="mt-2 font-semibold text-2xl text-slate-900">Provider Directory</h2>
                <p class="mt-1 text-sm text-slate-500">Review provider performance, inspect relevant details, and route into setup when changes are needed.</p>
            </div>
            <a href="{{ route('admin.scanning.providers.index') }}" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 transition">
                Back to Dashboard
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="flex flex-col gap-4 border-b border-slate-200 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Provider List</h3>
                        <p class="mt-1 text-sm text-slate-500">Clean inventory view for provider review, instant activation, and fast routing into setup for keys and endpoint configuration.</p>
                    </div>
                    <div class="inline-flex items-center rounded-xl bg-slate-50 px-3 py-2 text-xs font-medium text-slate-600">
                        {{ $providers->count() }} configured providers
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50/80">
                            <tr>
                                <th class="px-6 py-3 text-left font-medium uppercase tracking-wider text-slate-500">Provider</th>
                                <th class="px-6 py-3 text-left font-medium uppercase tracking-wider text-slate-500">Status</th>
                                <th class="px-6 py-3 text-left font-medium uppercase tracking-wider text-slate-500">Priority</th>
                                <th class="px-6 py-3 text-left font-medium uppercase tracking-wider text-slate-500">Families</th>
                                <th class="px-6 py-3 text-left font-medium uppercase tracking-wider text-slate-500">7d Success</th>
                                <th class="px-6 py-3 text-left font-medium uppercase tracking-wider text-slate-500">Latency</th>
                                <th class="px-6 py-3 text-left font-medium uppercase tracking-wider text-slate-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach($providers as $provider)
                                @php
                                    $providerModal = [
                                        'name' => $provider->name,
                                        'key' => $provider->provider_key,
                                        'health' => ucfirst($provider->health_status),
                                        'status' => $provider->is_active ? 'Active' : 'Disabled',
                                        'priority' => $provider->priority,
                                        'families' => $provider->supported_families ?? [],
                                        'attempts' => $provider->quality_metrics['attempts'] ?? 0,
                                        'success_rate' => $provider->quality_metrics['success_rate'],
                                        'match_rate' => $provider->quality_metrics['match_rate'],
                                        'error_rate' => $provider->quality_metrics['error_rate'],
                                        'ingredient_coverage' => $provider->quality_metrics['ingredient_coverage'],
                                        'nutrition_coverage' => $provider->quality_metrics['nutrition_coverage'],
                                        'average_confidence' => $provider->quality_metrics['average_confidence'],
                                        'average_completeness' => $provider->quality_metrics['average_completeness'],
                                        'median_latency_ms' => $provider->quality_metrics['median_latency_ms'],
                                        'notes' => $provider->notes,
                                        'driver' => $provider->driver,
                                        'recent_successes' => $provider->recent_successful_lookups_count,
                                        'recent_attempts' => $provider->recent_lookups_count,
                                    ];
                                @endphp
                                <tr class="hover:bg-slate-50/60 transition">
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-slate-900">{{ $provider->name }}</div>
                                        <div class="mt-1 text-xs text-slate-500">{{ $provider->provider_key }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col gap-2">
                                            <span class="inline-flex w-fit items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $provider->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                                {{ $provider->is_active ? 'Active' : 'Disabled' }}
                                            </span>
                                            <span class="inline-flex w-fit items-center rounded-full px-2.5 py-1 text-xs font-semibold
                                                {{ $provider->health_status === 'healthy' ? 'bg-emerald-50 text-emerald-700' : '' }}
                                                {{ $provider->health_status === 'degraded' ? 'bg-amber-100 text-amber-700' : '' }}
                                                {{ $provider->health_status === 'unknown' ? 'bg-slate-100 text-slate-600' : '' }}">
                                                {{ ucfirst($provider->health_status) }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-slate-700">{{ $provider->priority }}</td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-wrap gap-2">
                                            @forelse($provider->supported_families ?? [] as $family)
                                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                                                    {{ str_replace('_', ' ', $family) }}
                                                </span>
                                            @empty
                                                <span class="text-xs text-slate-500">All families</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-slate-900">{{ $provider->quality_metrics['success_rate'] !== null ? $provider->quality_metrics['success_rate'] . '%' : 'N/A' }}</div>
                                        <div class="mt-1 text-xs text-slate-500">{{ $provider->recent_successful_lookups_count }} / {{ $provider->recent_lookups_count }} recent</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-slate-900">{{ $provider->quality_metrics['median_latency_ms'] !== null ? $provider->quality_metrics['median_latency_ms'] . ' ms' : 'N/A' }}</div>
                                        <div class="mt-1 text-xs text-slate-500">Confidence {{ $provider->quality_metrics['average_confidence'] ?? 'N/A' }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <form method="POST" action="{{ route('admin.scanning.providers.toggle-active', $provider) }}">
                                                @csrf
                                                <button
                                                    type="submit"
                                                    class="inline-flex items-center rounded-md border px-2.5 py-1.5 text-xs font-medium transition {{ $provider->is_active ? 'border-red-200 bg-white text-red-700 hover:bg-red-50' : 'border-emerald-200 bg-white text-emerald-700 hover:bg-emerald-50' }}"
                                                >
                                                    {{ $provider->is_active ? 'Disable' : 'Enable' }}
                                                </button>
                                            </form>
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 transition"
                                                data-provider='@json($providerModal)'
                                                data-provider-view
                                            >
                                                View
                                            </button>
                                            <form method="GET" action="{{ route('admin.scanning.providers.edit', $provider) }}">
                                                <button
                                                    type="submit"
                                                    class="inline-flex items-center rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 transition"
                                                >
                                                    Edit Setup
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <dialog id="provider-details-modal" class="w-full max-w-3xl rounded-3xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-900/40">
        <div class="bg-white">
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
                <div>
                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Provider Details</div>
                    <h3 id="provider-modal-name" class="mt-1 text-xl font-semibold text-slate-900">Provider</h3>
                    <p id="provider-modal-key" class="mt-1 text-sm text-slate-500"></p>
                </div>
                <button type="button" id="provider-modal-close" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-50 transition">
                    <span class="sr-only">Close</span>
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                </button>
            </div>

            <div class="space-y-6 px-6 py-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Status</div>
                        <div id="provider-modal-status" class="mt-2 text-lg font-semibold text-slate-900"></div>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Health</div>
                        <div id="provider-modal-health" class="mt-2 text-lg font-semibold text-slate-900"></div>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Priority</div>
                        <div id="provider-modal-priority" class="mt-2 text-lg font-semibold text-slate-900"></div>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Median Latency</div>
                        <div id="provider-modal-latency" class="mt-2 text-lg font-semibold text-slate-900"></div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="rounded-2xl border border-slate-200 p-5">
                        <h4 class="text-sm font-semibold text-slate-900">Quality Metrics</h4>
                        <div class="mt-4 grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <div class="text-slate-500">7d Attempts</div>
                                <div id="provider-modal-attempts" class="mt-1 font-semibold text-slate-900"></div>
                            </div>
                            <div>
                                <div class="text-slate-500">Success Rate</div>
                                <div id="provider-modal-success" class="mt-1 font-semibold text-slate-900"></div>
                            </div>
                            <div>
                                <div class="text-slate-500">Match Rate</div>
                                <div id="provider-modal-match" class="mt-1 font-semibold text-slate-900"></div>
                            </div>
                            <div>
                                <div class="text-slate-500">Error Rate</div>
                                <div id="provider-modal-error" class="mt-1 font-semibold text-slate-900"></div>
                            </div>
                            <div>
                                <div class="text-slate-500">Ingredient Coverage</div>
                                <div id="provider-modal-ingredients" class="mt-1 font-semibold text-slate-900"></div>
                            </div>
                            <div>
                                <div class="text-slate-500">Nutrition Coverage</div>
                                <div id="provider-modal-nutrition" class="mt-1 font-semibold text-slate-900"></div>
                            </div>
                            <div>
                                <div class="text-slate-500">Avg Confidence</div>
                                <div id="provider-modal-confidence" class="mt-1 font-semibold text-slate-900"></div>
                            </div>
                            <div>
                                <div class="text-slate-500">Avg Completeness</div>
                                <div id="provider-modal-completeness" class="mt-1 font-semibold text-slate-900"></div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 p-5">
                        <h4 class="text-sm font-semibold text-slate-900">Relevant Details</h4>
                        <div class="mt-4 space-y-4 text-sm">
                            <div>
                                <div class="text-slate-500">Families</div>
                                <div id="provider-modal-families" class="mt-2 flex flex-wrap gap-2"></div>
                            </div>
                            <div>
                                <div class="text-slate-500">Driver</div>
                                <div id="provider-modal-driver" class="mt-1 font-medium text-slate-900 break-all"></div>
                            </div>
                            <div>
                                <div class="text-slate-500">Notes</div>
                                <div id="provider-modal-notes" class="mt-1 text-slate-700"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </dialog>

    <script>
        (() => {
            const modal = document.getElementById('provider-details-modal');
            const closeButton = document.getElementById('provider-modal-close');

            if (!modal) {
                return;
            }

            const text = {
                name: document.getElementById('provider-modal-name'),
                key: document.getElementById('provider-modal-key'),
                status: document.getElementById('provider-modal-status'),
                health: document.getElementById('provider-modal-health'),
                priority: document.getElementById('provider-modal-priority'),
                latency: document.getElementById('provider-modal-latency'),
                attempts: document.getElementById('provider-modal-attempts'),
                success: document.getElementById('provider-modal-success'),
                match: document.getElementById('provider-modal-match'),
                error: document.getElementById('provider-modal-error'),
                ingredients: document.getElementById('provider-modal-ingredients'),
                nutrition: document.getElementById('provider-modal-nutrition'),
                confidence: document.getElementById('provider-modal-confidence'),
                completeness: document.getElementById('provider-modal-completeness'),
                families: document.getElementById('provider-modal-families'),
                driver: document.getElementById('provider-modal-driver'),
                notes: document.getElementById('provider-modal-notes'),
            };

            const display = (value, suffix = '') => {
                if (value === null || value === undefined || value === '') {
                    return 'N/A';
                }

                return `${value}${suffix}`;
            };

            document.querySelectorAll('[data-provider-view]').forEach((button) => {
                button.addEventListener('click', () => {
                    const provider = JSON.parse(button.dataset.provider || '{}');

                    text.name.textContent = provider.name || 'Provider';
                    text.key.textContent = provider.key || '';
                    text.status.textContent = provider.status || 'N/A';
                    text.health.textContent = provider.health || 'N/A';
                    text.priority.textContent = display(provider.priority);
                    text.latency.textContent = display(provider.median_latency_ms, ' ms');
                    text.attempts.textContent = display(provider.attempts);
                    text.success.textContent = display(provider.success_rate, '%');
                    text.match.textContent = display(provider.match_rate, '%');
                    text.error.textContent = display(provider.error_rate, '%');
                    text.ingredients.textContent = display(provider.ingredient_coverage, '%');
                    text.nutrition.textContent = display(provider.nutrition_coverage, '%');
                    text.confidence.textContent = display(provider.average_confidence);
                    text.completeness.textContent = display(provider.average_completeness);
                    text.driver.textContent = provider.driver || 'N/A';
                    text.notes.textContent = provider.notes || 'No extra notes added yet.';

                    text.families.innerHTML = '';
                    const families = Array.isArray(provider.families) && provider.families.length > 0 ? provider.families : ['All families'];
                    families.forEach((family) => {
                        const chip = document.createElement('span');
                        chip.className = 'inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700';
                        chip.textContent = String(family).replaceAll('_', ' ');
                        text.families.appendChild(chip);
                    });

                    modal.showModal();
                });
            });

            closeButton?.addEventListener('click', () => modal.close());
            modal.addEventListener('click', (event) => {
                const rect = modal.getBoundingClientRect();
                const inside = rect.top <= event.clientY && event.clientY <= rect.top + rect.height
                    && rect.left <= event.clientX && event.clientX <= rect.left + rect.width;

                if (!inside) {
                    modal.close();
                }
            });
        })();
    </script>
</x-app-layout>
