<div class="flex flex-wrap gap-2">
    <a href="{{ route('admin.billing.index') }}" class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition {{ request()->routeIs('admin.billing.index') ? 'bg-[#1FA774] text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
        Overview
    </a>
    <a href="{{ route('admin.billing.invoices') }}" class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition {{ request()->routeIs('admin.billing.invoices') ? 'bg-[#1FA774] text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
        Invoices
    </a>
    <a href="{{ route('admin.billing.transactions') }}" class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition {{ request()->routeIs('admin.billing.transactions') ? 'bg-[#1FA774] text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
        Transactions
    </a>
    <a href="{{ route('admin.billing.refunds') }}" class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition {{ request()->routeIs('admin.billing.refunds') ? 'bg-[#1FA774] text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
        Refunds
    </a>
    <a href="{{ route('admin.billing.failures') }}" class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition {{ request()->routeIs('admin.billing.failures') ? 'bg-[#1FA774] text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
        Failed payments
    </a>
</div>
