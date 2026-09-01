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
                <a href="{{ route('admin.scanning.providers.directory') }}" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 transition">
                    Setup Providers
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
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
        </div>
    </div>
</x-app-layout>
