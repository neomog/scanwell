<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupportCaseRequest;
use App\Http\Requests\SupportMessageRequest;
use App\Http\Resources\SupportCaseResource;
use App\Http\Resources\SupportMessageResource;
use App\Models\SupportCase;
use App\Traits\ApiResponse;
use App\Services\SupportCaseService;
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
        $query = $request->user()->supportCases()
            ->with(['assignee'])
            ->latest('last_message_at')
            ->latest();

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $cases = $query->paginate((int) $request->get('per_page', 20));

        return $this->success([
            'cases' => collect($cases->items())
                ->map(fn ($item) => (new SupportCaseResource($item))->resolve())
                ->values(),
            'pagination' => $this->paginationMeta($cases),
        ], 'Support cases loaded');
    }

    public function store(SupportCaseRequest $request): JsonResponse
    {
        $supportCase = $this->supportCaseService->createForUser($request->user(), array_merge(
            $request->validated(),
            ['source' => 'mobile']
        ));

        return $this->success([
            'case' => (new SupportCaseResource($supportCase))->resolve(),
        ], 'Support case created successfully', 201);
    }

    public function show(Request $request, SupportCase $supportCase): JsonResponse
    {
        abort_unless($supportCase->user_id === $request->user()->id, 404);

        $supportCase->load([
            'user',
            'assignee',
            'messages' => fn ($query) => $query
                ->where('is_internal', false)
                ->with('user')
                ->orderBy('created_at'),
        ]);

        $this->supportCaseService->markViewedByCustomer($supportCase);

        return $this->success([
            'case' => (new SupportCaseResource($supportCase))->resolve(),
            'messages' => $supportCase->messages
                ->map(fn ($message) => (new SupportMessageResource($message))->resolve())
                ->values(),
        ], 'Support case loaded');
    }

    public function message(SupportMessageRequest $request, SupportCase $supportCase): JsonResponse
    {
        abort_unless($supportCase->user_id === $request->user()->id, 404);

        if (in_array($supportCase->status, [SupportCase::STATUS_RESOLVED, SupportCase::STATUS_CLOSED], true)) {
            return $this->error('Resolved or closed support cases cannot receive new customer messages.', null, 422);
        }

        $message = $this->supportCaseService->addMessage(
            $supportCase,
            $request->user(),
            $request->validated(),
            'customer'
        );

        return $this->success([
            'message_item' => (new SupportMessageResource($message))->resolve(),
            'case' => (new SupportCaseResource($supportCase->fresh(['user', 'assignee'])))->resolve(),
        ], 'Support message sent successfully', 201);
    }

    public function close(Request $request, SupportCase $supportCase): JsonResponse
    {
        abort_unless($supportCase->user_id === $request->user()->id, 404);

        $supportCase = $this->supportCaseService->updateCase($supportCase, [
            'status' => SupportCase::STATUS_CLOSED,
        ]);

        return $this->success([
            'case' => (new SupportCaseResource($supportCase))->resolve(),
        ], 'Support case closed successfully');
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
