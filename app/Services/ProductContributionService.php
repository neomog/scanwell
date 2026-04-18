<?php

namespace App\Services;

use App\Models\ProductContribution;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProductContributionService
{
    public function __construct(
        protected ProductWorkflowService $productWorkflowService,
        protected ProductPayloadService $productPayloadService,
        protected ContributionReputationService $contributionReputationService
    ) {
    }

    public function submit(User $user, string $barcode, array $input): ProductContribution
    {
        $product = $this->productWorkflowService->findByBarcode($barcode);
        $changeType = (string) ($input['change_type'] ?? 'update');
        $payload = $this->productPayloadService->fromInput(array_merge($input, ['barcode' => $barcode]));

        if ($product && $changeType !== 'add') {
            $payload = $this->productPayloadService->mergeForContribution($product, array_merge($input, ['barcode' => $barcode]));
        }

        $contribution = ProductContribution::create([
            'user_id' => $user->id,
            'product_id' => $product?->id,
            'change_type' => $changeType,
            'field_name' => $input['field_name'] ?? null,
            'old_data' => $product ? $this->productPayloadService->snapshotProduct($product) : null,
            'new_data' => $payload,
            'reason' => $input['reason'] ?? null,
            'evidence' => $input['evidence'] ?? null,
            'status' => 'pending',
            'barcode' => $barcode,
            'product_name' => $payload['name'] ?? $product?->name,
            'meta' => [
                'submitted_via' => $input['submitted_via'] ?? 'api',
            ],
        ]);

        $this->productWorkflowService->recordAudit(
            $user,
            $product,
            $contribution,
            'contribution_submitted',
            'Product contribution submitted',
            [
                'new' => $payload,
            ]
        );

        return $contribution->fresh(['user', 'product', 'reviewer']);
    }

    public function updateUserContribution(ProductContribution $contribution, array $input): ProductContribution
    {
        $payload = $this->productPayloadService->fromInput(array_merge($contribution->new_data ?? [], $input, [
            'barcode' => $input['barcode'] ?? $contribution->barcode,
        ]));

        $contribution->update([
            'field_name' => $input['field_name'] ?? $contribution->field_name,
            'new_data' => $payload,
            'reason' => $input['reason'] ?? $contribution->reason,
            'evidence' => $input['evidence'] ?? $contribution->evidence,
            'product_name' => $payload['name'] ?? $contribution->product_name,
            'barcode' => $payload['barcode'] ?? $contribution->barcode,
        ]);

        $this->productWorkflowService->recordAudit(
            $contribution->user,
            $contribution->product,
            $contribution,
            'contribution_updated_by_user',
            'Contributor updated pending submission',
            [
                'new' => $payload,
            ]
        );

        return $contribution->fresh(['user', 'product', 'reviewer']);
    }

    public function updateForModeration(ProductContribution $contribution, User $admin, array $input): ProductContribution
    {
        $merged = $this->productPayloadService->fromInput(array_merge($contribution->new_data ?? [], $input, [
            'barcode' => $input['barcode'] ?? $contribution->barcode,
        ]));

        $contribution->update([
            'moderated_data' => $merged,
            'product_name' => $merged['name'] ?? $contribution->product_name,
            'barcode' => $merged['barcode'] ?? $contribution->barcode,
            'review_notes' => $input['review_notes'] ?? $contribution->review_notes,
        ]);

        $this->productWorkflowService->recordAudit(
            $admin,
            $contribution->product,
            $contribution,
            'contribution_updated_by_admin',
            'Moderator edited contribution before decision',
            [
                'new' => $merged,
            ]
        );

        return $contribution->fresh(['user', 'product', 'reviewer']);
    }

    public function approve(ProductContribution $contribution, User $admin, array $overrides = [], ?string $notes = null): array
    {
        return DB::transaction(function () use ($contribution, $admin, $overrides, $notes) {
            $product = $contribution->product;
            $approvedPayload = $this->buildApprovedPayload($contribution, $overrides);

            if ($contribution->change_type === 'add') {
                if (!$product) {
                    $product = $this->productWorkflowService->findByBarcode($contribution->barcode);
                }

                if ($product) {
                    $product = $this->productWorkflowService->updateProduct($product, $approvedPayload, [
                        'actor' => $admin,
                        'contribution' => $contribution,
                        'audit_action' => 'community_product_merged',
                        'audit_description' => 'Approved community submission merged into existing product',
                        'track_manual_overrides' => true,
                        'mark_approved' => true,
                    ]);
                } else {
                    $product = $this->productWorkflowService->createProduct($approvedPayload, [
                        'actor' => $admin,
                        'source' => 'community',
                        'contribution' => $contribution,
                        'audit_action' => 'community_product_created',
                        'audit_description' => 'Approved community submission created a new product',
                        'mark_approved' => true,
                    ]);
                }
            } elseif ($product) {
                $product = $this->productWorkflowService->updateProduct($product, $approvedPayload, [
                    'actor' => $admin,
                    'contribution' => $contribution,
                    'audit_action' => 'community_product_updated',
                    'audit_description' => 'Approved community correction updated an existing product',
                    'track_manual_overrides' => true,
                    'mark_approved' => true,
                ]);
            }

            $contribution->update([
                'product_id' => $product?->id,
                'moderated_data' => $approvedPayload,
            ]);

            $contribution->approve($admin, $notes);
            $points = $this->contributionReputationService->applyApproval($contribution->user, $contribution);
            $contribution->update(['reputation_points_awarded' => $points]);

            ProductContribution::where('barcode', $contribution->barcode)
                ->whereNull('product_id')
                ->update(['product_id' => $product?->id]);

            $this->productWorkflowService->recordAudit(
                $admin,
                $product,
                $contribution,
                'contribution_approved',
                'Contribution approved',
                [
                    'new' => $approvedPayload,
                ],
                [
                    'points_awarded' => $points,
                    'notes' => $notes,
                ]
            );

            return [
                'contribution' => $contribution->fresh(['user', 'product', 'reviewer']),
                'product' => $product?->fresh(['ingredients', 'nutrition', 'foodScore', 'cosmeticScore', 'images', 'barcodes']),
                'points_awarded' => $points,
            ];
        });
    }

    public function reject(ProductContribution $contribution, User $admin, string $reason): ProductContribution
    {
        $contribution->reject($admin, $reason);
        $points = $this->contributionReputationService->applyRejection($contribution->user, $contribution);
        $contribution->update(['reputation_points_awarded' => $points]);

        $this->productWorkflowService->recordAudit(
            $admin,
            $contribution->product,
            $contribution,
            'contribution_rejected',
            'Contribution rejected',
            null,
            [
                'reason' => $reason,
                'points_awarded' => $points,
            ]
        );

        return $contribution->fresh(['user', 'product', 'reviewer']);
    }

    public function flag(ProductContribution $contribution, User $admin, string $reason): ProductContribution
    {
        $contribution->flag($admin, $reason);

        $this->productWorkflowService->recordAudit(
            $admin,
            $contribution->product,
            $contribution,
            'contribution_flagged',
            'Contribution flagged',
            null,
            ['reason' => $reason]
        );

        return $contribution->fresh(['user', 'product', 'reviewer']);
    }

    public function pendingSummaryForBarcode(string $barcode): ?ProductContribution
    {
        return ProductContribution::with('user')
            ->where('barcode', $barcode)
            ->whereIn('status', ['pending', 'changes_requested'])
            ->latest()
            ->first();
    }

    public function leaderboard(int $perPage = 20): LengthAwarePaginator
    {
        return User::query()
            ->where(function (Builder $query) {
                $query->where('reputation_points', '>', 0)
                    ->orWhere('approved_contributions_count', '>', 0);
            })
            ->orderByDesc('reputation_points')
            ->orderByDesc('approved_contributions_count')
            ->paginate($perPage);
    }

    protected function buildApprovedPayload(ProductContribution $contribution, array $overrides = []): array
    {
        return $this->productPayloadService->fromInput(array_merge(
            $contribution->new_data ?? [],
            $contribution->moderated_data ?? [],
            $overrides,
            ['barcode' => $overrides['barcode'] ?? $contribution->barcode]
        ));
    }
}
