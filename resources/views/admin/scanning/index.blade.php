<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold tracking-wide text-emerald-700">
                    Scan Operations
                </div>
                <h2 class="mt-3 font-semibold text-2xl text-slate-900">Provider Command Center</h2>
                <p class="mt-1 max-w-2xl text-sm text-slate-500">Monitor scan provider quality first, then move into setup only when you need to tune keys, endpoints, or import settings.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="#provider-directory" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 transition">
                    Setup Providers
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            <section class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
                    <div class="absolute right-0 top-0 h-16 w-16 rounded-full bg-emerald-100/60 blur-2xl"></div>
                    <div class="relative">
                        <div class="flex items-start justify-between gap-3">
                            <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a1 1 0 0 1 .894.553l1.382 2.8 3.09.45a1 1 0 0 1 .554 1.706l-2.236 2.18.528 3.078a1 1 0 0 1-1.45 1.054L10 12.347l-2.763 1.454a1 1 0 0 1-1.45-1.054l.528-3.079-2.236-2.179a1 1 0 0 1 .554-1.706l3.09-.45 1.382-2.8A1 1 0 0 1 10 2Z"/></svg>
                            </span>
                            <span class="text-[11px] font-semibold text-emerald-600">{{ $overview['active'] }}/{{ $overview['providers'] }} live</span>
                        </div>
                        <div class="mt-4 flex items-end justify-between gap-4">
                            <div>
                                <div class="text-2xl font-semibold leading-none text-slate-900">{{ $overview['providers'] }}</div>
                                <div class="mt-1 text-xs text-slate-500">Total Providers</div>
                            </div>
                            <div class="h-8 w-20 shrink-0">
                                <svg viewBox="0 0 120 40" class="h-full w-full text-emerald-400">
                                <path d="M0 22 C14 10, 26 10, 40 18 S66 32, 80 20 S102 10, 120 16" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
                    <div class="absolute right-0 top-0 h-16 w-16 rounded-full bg-sky-100/60 blur-2xl"></div>
                    <div class="relative">
                        <div class="flex items-start justify-between gap-3">
                            <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-600">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a2 2 0 0 1 2-2h5.586A2 2 0 0 1 12 2.586L16.414 7A2 2 0 0 1 17 8.414V16a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4Zm8 0v3a1 1 0 0 0 1 1h3"/></svg>
                            </span>
                            <span class="text-[11px] font-semibold text-sky-600">{{ $overview['recent_successes'] }} resolved</span>
                        </div>
                        <div class="mt-4 flex items-end justify-between gap-4">
                            <div>
                                <div class="text-2xl font-semibold leading-none text-slate-900">{{ $overview['recent_attempts'] }}</div>
                                <div class="mt-1 text-xs text-slate-500">7-Day Attempts</div>
                            </div>
                            <div class="h-8 w-20 shrink-0">
                                <svg viewBox="0 0 120 40" class="h-full w-full text-sky-400">
                                <path d="M0 24 L18 24 L28 12 L42 26 L58 14 L74 20 L92 11 L120 16" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
                    <div class="absolute right-0 top-0 h-16 w-16 rounded-full bg-violet-100/60 blur-2xl"></div>
                    <div class="relative">
                        <div class="flex items-start justify-between gap-3">
                            <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-600">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 3a7 7 0 1 0 0 14 7 7 0 0 0 0-14Zm1 3a1 1 0 1 0-2 0v3a1 1 0 0 0 .293.707l2 2a1 1 0 1 0 1.414-1.414L11 8.586V6Z"/></svg>
                            </span>
                            <span class="text-[11px] font-semibold text-violet-600">{{ $overview['healthy'] }} healthy</span>
                        </div>
                        <div class="mt-4 flex items-end justify-between gap-4">
                            <div>
                                <div class="text-2xl font-semibold leading-none text-slate-900">{{ $overview['active'] }}</div>
                                <div class="mt-1 text-xs text-slate-500">Active Providers</div>
                            </div>
                            <div class="h-8 w-20 shrink-0">
                                <svg viewBox="0 0 120 40" class="h-full w-full text-violet-400">
                                <path d="M0 18 C12 18, 18 10, 28 10 S50 28, 64 28 S88 12, 104 18 S114 24, 120 22" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
                    <div class="absolute right-0 top-0 h-16 w-16 rounded-full bg-amber-100/60 blur-2xl"></div>
                    <div class="relative">
                        <div class="flex items-start justify-between gap-3">
                            <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a8 8 0 1 0 8 8 8 8 0 0 0-8-8Zm0 4a1 1 0 0 1 1 1v3a1 1 0 1 1-2 0V7a1 1 0 0 1 1-1Zm0 8a1.25 1.25 0 1 1 0-2.5 1.25 1.25 0 0 1 0 2.5Z"/></svg>
                            </span>
                            <span class="text-[11px] font-semibold {{ $overview['degraded'] > 0 ? 'text-amber-600' : 'text-emerald-600' }}">
                                {{ $overview['degraded'] > 0 ? 'attention needed' : 'all clear' }}
                            </span>
                        </div>
                        <div class="mt-4 flex items-end justify-between gap-4">
                            <div>
                                <div class="text-2xl font-semibold leading-none text-slate-900">{{ $overview['degraded'] }}</div>
                                <div class="mt-1 text-xs text-slate-500">Degraded Providers</div>
                            </div>
                            <div class="flex h-8 items-end gap-1.5 shrink-0">
                                <span class="block w-2 rounded-full bg-amber-500" style="height: 60%"></span>
                                <span class="block w-2 rounded-full bg-amber-400" style="height: 35%"></span>
                                <span class="block w-2 rounded-full bg-amber-500" style="height: 72%"></span>
                                <span class="block w-2 rounded-full bg-amber-300" style="height: 48%"></span>
                                <span class="block w-2 rounded-full bg-amber-500" style="height: 82%"></span>
                                <span class="block w-2 rounded-full bg-amber-400" style="height: 58%"></span>
                                <span class="block w-2 rounded-full bg-amber-500" style="height: 40%"></span>
                                <span class="block w-2 rounded-full bg-amber-300" style="height: 66%"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="flex flex-col gap-4 border-b border-slate-200 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Provider Attempts</h3>
                        <p class="mt-1 text-sm text-slate-500">Latest provider lookups across scans, ordered by recency.</p>
                    </div>
                    <div class="inline-flex items-center rounded-xl bg-slate-50 px-3 py-2 text-xs font-medium text-slate-600">
                        Rolling operational feed
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50/80">
                            <tr>
                                <th class="px-6 py-3 text-left font-medium uppercase tracking-wider text-slate-500">Provider</th>
                                <th class="px-6 py-3 text-left font-medium uppercase tracking-wider text-slate-500">Barcode</th>
                                <th class="px-6 py-3 text-left font-medium uppercase tracking-wider text-slate-500">Status</th>
                                <th class="px-6 py-3 text-left font-medium uppercase tracking-wider text-slate-500">Family</th>
                                <th class="px-6 py-3 text-left font-medium uppercase tracking-wider text-slate-500">Latency</th>
                                <th class="px-6 py-3 text-left font-medium uppercase tracking-wider text-slate-500">Confidence</th>
                                <th class="px-6 py-3 text-left font-medium uppercase tracking-wider text-slate-500">Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse($recentLookups as $lookup)
                                <tr class="hover:bg-slate-50/60 transition">
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-slate-900">{{ $lookup->provider?->name ?? 'Unknown' }}</div>
                                        <div class="mt-1 text-xs text-slate-500">{{ $lookup->provider?->provider_key ?? 'unknown' }}</div>
                                    </td>
                                    <td class="px-6 py-4 font-mono text-slate-700">{{ $lookup->barcode }}</td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold
                                            {{ $lookup->status === 'success' ? 'bg-emerald-100 text-emerald-700' : '' }}
                                            {{ $lookup->status === 'error' ? 'bg-red-100 text-red-700' : '' }}
                                            {{ in_array($lookup->status, ['not_found', 'rejected'], true) ? 'bg-amber-100 text-amber-700' : '' }}">
                                            {{ str_replace('_', ' ', ucfirst($lookup->status)) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-slate-600">{{ $lookup->product_family ? str_replace('_', ' ', ucfirst($lookup->product_family)) : 'N/A' }}</td>
                                    <td class="px-6 py-4 text-slate-700">{{ $lookup->latency_ms ? $lookup->latency_ms . ' ms' : 'N/A' }}</td>
                                    <td class="px-6 py-4 text-slate-700">{{ $lookup->confidence ?? 'N/A' }}</td>
                                    <td class="px-6 py-4 text-slate-500">{{ $lookup->created_at?->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-10 text-center text-slate-500">No provider attempts recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section id="provider-directory" class="rounded-[28px] border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="flex flex-col gap-4 border-b border-slate-200 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Provider Directory</h3>
                        <p class="mt-1 text-sm text-slate-500">A cleaner operational inventory with lightweight inspection and dedicated setup entry points.</p>
                    </div>
                    <a href="#provider-directory" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                        Provider List
                    </a>
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
                                        <div class="flex items-center gap-2">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition"
                                                data-provider='@json($providerModal)'
                                                data-provider-view
                                            >
                                                View
                                            </button>
                                            <a href="{{ route('admin.scanning.providers.edit', $provider) }}" class="inline-flex items-center rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 transition">
                                                Edit
                                            </a>
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
