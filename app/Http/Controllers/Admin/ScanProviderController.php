<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ScanProvider;
use App\Models\ScanProviderLookup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ScanProviderController extends Controller
{
    public function index(): View
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
            'settings_json' => 'nullable|string',
            'credentials_json' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $scanProvider->update([
            'name' => $validated['name'],
            'priority' => $validated['priority'],
            'supported_families' => $this->parseFamilies($validated['supported_families'] ?? ''),
            'timeout_seconds' => $validated['timeout_seconds'],
            'retry_attempts' => $validated['retry_attempts'],
            'cache_ttl_minutes' => $validated['cache_ttl_minutes'],
            'settings' => $this->parseJsonField($validated['settings_json'] ?? '', 'settings JSON'),
            'credentials' => $this->parseJsonField($validated['credentials_json'] ?? '', 'credentials JSON'),
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

    protected function parseFamilies(string $value): array
    {
        return collect(preg_split('/[\s,]+/', $value))
            ->map(fn (?string $family) => trim((string) $family))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function parseJsonField(string $value, string $fieldName): array
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return [];
        }

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw ValidationException::withMessages([
                str_replace(' ', '_', strtolower($fieldName)) => "The {$fieldName} field must contain valid JSON.",
            ]);
        }

        return is_array($decoded) ? $decoded : [];
    }
}
