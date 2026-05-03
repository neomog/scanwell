<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminSupportCaseUpdateRequest;
use App\Http\Requests\SupportMessageRequest;
use App\Models\SupportCase;
use App\Models\User;
use App\Services\SupportCaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportCaseController extends Controller
{
    public function __construct(
        protected SupportCaseService $supportCaseService
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('support.view'), 403);

        $query = SupportCase::query()
            ->with(['user', 'assignee'])
            ->latest('last_message_at')
            ->latest();

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($priority = $request->get('priority')) {
            $query->where('priority', $priority);
        }

        if ($assigned = $request->get('assigned')) {
            if ($assigned === 'me') {
                $query->where('assigned_to', $request->user()->id);
            } elseif ($assigned === 'unassigned') {
                $query->whereNull('assigned_to');
            }
        }

        if ($search = trim((string) $request->get('search'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('reference', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($userQuery) => $userQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        return view('admin.support.index', [
            'cases' => $query->paginate(15)->withQueryString(),
            'types' => config('support.types', []),
            'statuses' => config('support.statuses', []),
            'priorities' => config('support.priorities', []),
            'stats' => [
                'total' => SupportCase::count(),
                'open' => SupportCase::whereIn('status', [SupportCase::STATUS_OPEN, SupportCase::STATUS_PENDING_SUPPORT, SupportCase::STATUS_PENDING_USER])->count(),
                'bugs' => SupportCase::where('type', SupportCase::TYPE_BUG_REPORT)->count(),
                'unassigned' => SupportCase::whereNull('assigned_to')->count(),
            ],
        ]);
    }

    public function show(Request $request, SupportCase $supportCase): View
    {
        abort_unless($request->user()->can('support.view'), 403);

        $supportCase->load([
            'user',
            'assignee',
            'messages.user',
        ]);

        $this->supportCaseService->markViewedBySupport($supportCase);

        return view('admin.support.show', [
            'supportCase' => $supportCase,
            'types' => config('support.types', []),
            'statuses' => config('support.statuses', []),
            'priorities' => config('support.priorities', []),
            'agents' => User::query()
                ->whereIn('role', ['super_admin', 'admin', 'support'])
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role']),
        ]);
    }

    public function update(AdminSupportCaseUpdateRequest $request, SupportCase $supportCase): RedirectResponse
    {
        abort_unless($request->user()->can('support.manage'), 403);

        $this->supportCaseService->updateCase($supportCase, $request->validated());

        return redirect()
            ->route('admin.support.show', $supportCase)
            ->with('success', 'Support case updated successfully.');
    }

    public function message(SupportMessageRequest $request, SupportCase $supportCase): RedirectResponse
    {
        abort_unless($request->user()->can('support.manage'), 403);

        $this->supportCaseService->addMessage(
            $supportCase,
            $request->user(),
            $request->validated(),
            'support'
        );

        return redirect()
            ->route('admin.support.show', $supportCase)
            ->with('success', 'Reply sent successfully.');
    }
}
