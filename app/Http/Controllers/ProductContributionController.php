<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductContributionResource;
use App\Models\Product;
use App\Models\ProductContribution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ProductContributionController extends Controller
{
    /**
     * Store a new product contribution
     */
    public function store(Request $request, string $barcode): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'change_type' => 'required|string|in:add,update,correct,report_issue',
            'field_name' => 'required_if:change_type,update,correct|string',
            'old_value' => 'sometimes',
            'new_value' => 'required',
            'reason' => 'required|string|min:10',
            'evidence' => 'sometimes|array',
            'evidence.*' => 'url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Find or create product reference
        $product = Product::where('barcode', $barcode)->first();

        $contribution = ProductContribution::create([
            'user_id' => Auth::id(),
            'product_id' => $product?->id,
            'change_type' => $request->change_type,
            'old_data' => $request->old_value ? [$request->field_name => $request->old_value] : null,
            'new_data' => [$request->field_name => $request->new_value],
            'reason' => $request->reason,
            'evidence' => $request->evidence,
            'status' => 'pending',
            'barcode' => $barcode,
            'product_name' => $request->product_name ?? $product?->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contribution submitted successfully. Thank you for helping improve our database!',
            'data' => new ProductContributionResource($contribution),
        ], 201);
    }

    /**
     * Get user's contributions
     */
    public function userContributions(): JsonResponse
    {
        $contributions = ProductContribution::where('user_id', Auth::id())
            ->with(['product', 'reviewer'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => ProductContributionResource::collection($contributions),
            'meta' => [
                'total' => $contributions->total(),
                'per_page' => $contributions->perPage(),
                'current_page' => $contributions->currentPage(),
                'last_page' => $contributions->lastPage(),
            ],
        ]);
    }

    /**
     * Get pending contributions (admin only)
     */
    public function pending(): JsonResponse
    {
        $this->authorize('viewPending', ProductContribution::class);

        $contributions = ProductContribution::with(['user', 'product', 'reviewer'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => ProductContributionResource::collection($contributions),
            'meta' => [
                'total' => $contributions->total(),
                'pending_count' => ProductContribution::where('status', 'pending')->count(),
            ],
        ]);
    }

    /**
     * Approve a contribution (admin only)
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $this->authorize('approve', ProductContribution::class);

        $contribution = ProductContribution::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $contribution->approve(Auth::user(), $request->notes);

        return response()->json([
            'success' => true,
            'message' => 'Contribution approved successfully',
            'data' => new ProductContributionResource($contribution->load(['user', 'product', 'reviewer'])),
        ]);
    }

    /**
     * Reject a contribution (admin only)
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $this->authorize('reject', ProductContribution::class);

        $contribution = ProductContribution::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|min:10|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $contribution->reject(Auth::user(), $request->reason);

        return response()->json([
            'success' => true,
            'message' => 'Contribution rejected',
            'data' => new ProductContributionResource($contribution->load(['user', 'product', 'reviewer'])),
        ]);
    }
}
