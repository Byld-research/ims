<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Purchase orders')" :subtitle="$site ? $site->code.' · '.$site->name : __('All sites')">
            <x-export-link />
            @if ($site ? auth()->user()->can('create', [App\Models\PurchaseOrder::class, $site]) : auth()->user()->isAdmin())
                <a href="{{ route('purchase-orders.create') }}" class="btn-primary">{{ __('New order') }}</a>
            @endif
        </x-page-header>
    </x-slot>

    <x-page>
        <form method="GET" class="card card-body grid gap-3 sm:grid-cols-5 items-end">
            <div>
                <label for="q" class="block text-xs font-medium text-gray-600">{{ __('Number or tracking') }}</label>
                <input type="search" name="q" id="q" value="{{ $filters['q'] ?? '' }}" class="form-input mt-1" placeholder="PO-2026-…">
            </div>
            <div class="sm:col-span-2">
                <label for="status" class="block text-xs font-medium text-gray-600">{{ __('Status') }}</label>
                <x-select name="status" :options="$statuses" :value="$filters['status'] ?? null" :placeholder="__('Any status')" class="mt-1" />
            </div>
            <div>
                <label for="supplier" class="block text-xs font-medium text-gray-600">{{ __('Supplier') }}</label>
                <x-select name="supplier" :options="$suppliers" :value="$filters['supplier'] ?? null" :placeholder="__('Any supplier')" class="mt-1" />
            </div>
            <button class="btn-primary btn-sm">{{ __('Filter') }}</button>
        </form>

        <div class="card table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Number') }}</th>
                        @unless ($site)<th>{{ __('Site') }}</th>@endunless
                        <th>{{ __('Supplier') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Ordered') }}</th>
                        <th>{{ __('ETA') }}</th>
                        <th>{{ __('Tracking') }}</th>
                        <th class="num">{{ __('Lines') }}</th>
                        <th class="num">{{ __('Value') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        @php($overdue = $order->eta && $order->eta->isPast() && ! $order->eta->isToday() && in_array($order->status, [App\Enums\PurchaseOrderStatus::Ordered, App\Enums\PurchaseOrderStatus::Confirmed, App\Enums\PurchaseOrderStatus::Shipped], true))
                        <tr>
                            <td class="font-mono whitespace-nowrap"><a class="link" href="{{ route('purchase-orders.show', $order) }}">{{ $order->number }}</a></td>
                            @unless ($site)<td>{{ $order->site->code }}</td>@endunless
                            <td>{{ $order->supplier->name }}</td>
                            <td><x-po-status :status="$order->status" /></td>
                            <td class="whitespace-nowrap text-gray-600">{{ \App\Support\Format::date($order->ordered_at) }}</td>
                            <td class="whitespace-nowrap {{ $overdue ? 'text-red-700 font-semibold' : 'text-gray-600' }}">
                                {{ \App\Support\Format::date($order->eta) }}
                                @if ($overdue)<span class="badge-red ms-1">{{ __('Late') }}</span>@endif
                            </td>
                            <td class="text-gray-600 max-w-48 truncate">{{ $order->tracking_ref }}</td>
                            <td class="num">{{ $order->lines_count }}</td>
                            <td class="num">{{ \App\Support\Format::money($order->total) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="py-6 text-center text-gray-500">{{ __('No orders match.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $orders->links() }}
    </x-page>
</x-app-layout>
