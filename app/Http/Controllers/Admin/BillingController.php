<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingInvoice;
use App\Models\BillingRefund;
use App\Models\BillingTransaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(): View
    {
        $paidInvoicesQuery = BillingInvoice::query()->whereNotNull('paid_at');
        $refundsQuery = BillingRefund::query();

        return view('admin.billing.index', [
            'metrics' => [
                'paid_invoices' => (clone $paidInvoicesQuery)->count(),
                'open_invoices' => BillingInvoice::query()->where('amount_due', '>', 0)->count(),
                'failed_payments' => BillingInvoice::query()->whereNotNull('failed_at')->count(),
                'refunds_issued' => (clone $refundsQuery)->count(),
                'revenue_cents' => (int) (clone $paidInvoicesQuery)->sum('amount_paid'),
                'refund_total_cents' => (int) (clone $refundsQuery)->sum('amount'),
            ],
            'recentFailures' => BillingInvoice::query()
                ->with(['user', 'subscription.plan'])
                ->whereNotNull('failed_at')
                ->latest('failed_at')
                ->limit(5)
                ->get(),
            'recentRefunds' => BillingRefund::query()
                ->with(['user', 'subscription.plan'])
                ->latest('refunded_at')
                ->limit(5)
                ->get(),
        ]);
    }

    public function invoices(Request $request): View
    {
        $query = BillingInvoice::query()
            ->with(['user', 'subscription.plan', 'subscription.price'])
            ->latest('issued_at')
            ->latest('created_at');

        if ($status = $request->input('status')) {
            if ($status === 'failed') {
                $query->whereNotNull('failed_at');
            } else {
                $query->where('status', $status);
            }
        }

        if ($search = trim((string) $request->input('search'))) {
            $query->whereHas('user', function ($userQuery) use ($search) {
                $userQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return view('admin.billing.invoices', [
            'invoices' => $query->paginate(20)->withQueryString(),
        ]);
    }

    public function transactions(Request $request): View
    {
        $query = BillingTransaction::query()
            ->with(['user', 'subscription.plan', 'invoice'])
            ->latest('occurred_at')
            ->latest('created_at');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->input('search'))) {
            $query->whereHas('user', function ($userQuery) use ($search) {
                $userQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return view('admin.billing.transactions', [
            'transactions' => $query->paginate(20)->withQueryString(),
        ]);
    }

    public function refunds(Request $request): View
    {
        $query = BillingRefund::query()
            ->with(['user', 'subscription.plan', 'invoice'])
            ->latest('refunded_at')
            ->latest('created_at');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->input('search'))) {
            $query->whereHas('user', function ($userQuery) use ($search) {
                $userQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return view('admin.billing.refunds', [
            'refunds' => $query->paginate(20)->withQueryString(),
        ]);
    }

    public function failures(Request $request): View
    {
        $query = BillingInvoice::query()
            ->with(['user', 'subscription.plan', 'subscription.price'])
            ->whereNotNull('failed_at')
            ->latest('failed_at');

        if ($search = trim((string) $request->input('search'))) {
            $query->whereHas('user', function ($userQuery) use ($search) {
                $userQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return view('admin.billing.failures', [
            'failedInvoices' => $query->paginate(20)->withQueryString(),
        ]);
    }
}
