<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminSupportCaseUpdateRequest;
use App\Http\Requests\SupportMessageRequest;
use App\Http\Resources\SupportCaseResource;
use App\Http\Resources\SupportMessageResource;
use App\Models\SupportCase;
use App\Services\SupportCaseService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class SupportCaseController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected SupportCaseService $supportCaseService
    ) {
    }

    public function index(Request $request): JsonResponse
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

        $cases = $query->paginate((int) $request->get('per_page', 20));

        return $this->success([
            'cases' => collect($cases->items())
                ->map(fn ($item) => (new SupportCaseResource($item))->resolve())
                ->values(),
            'pagination' => $this->paginationMeta($cases),
            'stats' => [
                'total' => SupportCase::count(),
                'pending_support' => SupportCase::where('status', SupportCase::STATUS_PENDING_SUPPORT)->count(),
                'pending_user' => SupportCase::where('status', SupportCase::STATUS_PENDING_USER)->count(),
                'unassigned' => SupportCase::whereNull('assigned_to')->count(),
            ],
        ], 'Support cases loaded');
    }

    public function show(Request $request, SupportCase $supportCase): JsonResponse
    {
        abort_unless($request->user()->can('support.view'), 403);

        $supportCase->load(['user', 'assignee', 'messages.user']);
        $this->supportCaseService->markViewedBySupport($supportCase);

        return $this->success([
            'case' => (new SupportCaseResource($supportCase))->resolve(),
            'messages' => $supportCase->messages
                ->map(fn ($message) => (new SupportMessageResource($message))->resolve())
                ->values(),
        ], 'Support case loaded');
    }

    public function update(AdminSupportCaseUpdateRequest $request, SupportCase $supportCase): JsonResponse
    {
        abort_unless($request->user()->can('support.manage'), 403);

        $supportCase = $this->supportCaseService->updateCase($supportCase, $request->validated());

        return $this->success([
            'case' => (new SupportCaseResource($supportCase))->resolve(),
        ], 'Support case updated successfully');
    }

    public function message(SupportMessageRequest $request, SupportCase $supportCase): JsonResponse
    {
        abort_unless($request->user()->can('support.manage'), 403);

        $message = $this->supportCaseService->addMessage(
            $supportCase,
            $request->user(),
            $request->validated(),
            'support'
        );

        return $this->success([
            'message_item' => (new SupportMessageResource($message))->resolve(),
            'case' => (new SupportCaseResource($supportCase->fresh(['user', 'assignee'])))->resolve(),
        ], 'Support reply sent successfully', 201);
    }

    protected function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
