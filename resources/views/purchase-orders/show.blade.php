@php
    use App\Enums\PurchaseOrderStatus as S;
    use App\Support\Format;

    $canEdit = auth()->user()->can('update', $order);
    $draft = $order->status === S::Draft;
    $receiving = $order->status->acceptsReceipts();
    $overdue = $order->eta && $order->eta->isPast() && ! $order->eta->isToday() && in_array($order->status, [S::Ordered, S::Confirmed, S::Shipped], true);
    $trackingIsUrl = $order->tracking_ref && filter_var($order->tracking_ref, FILTER_VALIDATE_URL);
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$order->number" :subtitle="$order->supplier->name.' → '.$order->site->code.' · '.$order->site->name">
            <x-po-status :status="$order->status" class="text-sm" />
            @if ($receiving && auth()->user()->can('receive', $order))
                <a href="{{ route('purchase-orders.receive', $order) }}" class="btn-primary">{{ __('Receive goods') }}</a>
            @endif
        </x-page-header>
    </x-slot>

    <x-page>
        @if ($errors->hasAny(['order', 'reason', 'eta', 'tracking_ref', 'unit_price', 'qty_ordered', 'item_id']))
            <div class="rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800" role="alert">
                {{ $errors->first('order') ?: $errors->first() }}
            </div>
        @endif

        {{-- Next step for this status (SPEC 5.3) --}}
        @if ($canEdit && ! in_array($order->status, [S::Closed, S::Cancelled], true))
            <section class="card card-body flex flex-wrap items-end gap-4" x-data="{ cancelling: false }">
                @switch($order->status)
                    @case(S::Draft)
                        <form method="POST" action="{{ route('purchase-orders.order', $order) }}">
                            @csrf
                            <button class="btn-primary" @disabled($order->lines->isEmpty())>{{ __('Mark as sent to supplier') }}</button>
                        </form>
                        <p class="text-sm text-gray-500 self-center">{{ __('Send the order to the supplier yourself, then mark it here. Lines can no longer change after that.') }}</p>
                        @break
                    @case(S::Ordered)
                        <form method="POST" action="{{ route('purchase-orders.confirm', $order) }}" class="flex items-end gap-2">
                            @csrf
                            <x-field name="eta" :label="__('Delivery date confirmed by the supplier')">
                                <x-input type="date" name="eta" required />
                            </x-field>
                            <button class="btn-primary">{{ __('Record confirmation') }}</button>
                        </form>
                        @break
                    @case(S::Confirmed)
                        <form method="POST" action="{{ route('purchase-orders.ship', $order) }}" class="flex items-end gap-2">
                            @csrf
                            <x-field name="tracking_ref" :label="__('Tracking number or link')">
                                <x-input name="tracking_ref" required maxlength="200" class="w-80" />
                            </x-field>
                            <button class="btn-primary">{{ __('Record shipment') }}</button>
                        </form>
                        @break
                    @case(S::Received)
                        <form method="POST" action="{{ route('purchase-orders.close', $order) }}">
                            @csrf
                            <button class="btn-primary">{{ __('Close order') }}</button>
                        </form>
                        <p class="text-sm text-gray-500 self-center">{{ __('Close once payment is settled. This has no accounting meaning.') }}</p>
                        @break
                @endswitch

                @if ($order->status->canTransitionTo(S::Cancelled) && $order->lines->every(fn ($l) => bccomp($l->qty_received, '0', 3) === 0))
                    <button type="button" class="btn-link-danger ms-auto self-center" x-show="!cancelling" @click="cancelling = true">{{ __('Cancel order…') }}</button>
                    <form method="POST" action="{{ route('purchase-orders.cancel', $order) }}" class="ms-auto flex items-end gap-2" x-show="cancelling" x-cloak>
                        @csrf
                        <x-field name="reason" :label="__('Why is it cancelled?')">
                            <x-input name="reason" required maxlength="500" class="w-72" />
                        </x-field>
                        <button class="btn-secondary text-red-700">{{ __('Cancel order') }}</button>
                        <button type="button" class="btn-secondary" @click="cancelling = false">{{ __('Keep') }}</button>
                    </form>
                @endif
            </section>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Lines --}}
            <section class="card lg:col-span-2">
                <div class="card-body pb-2 flex items-center justify-between">
                    <h3 class="card-title">{{ __('Lines') }}</h3>
                    <span class="text-sm text-gray-600">{{ __('Total') }} <span class="font-semibold tabular-nums">{{ Format::money($total) }}</span> USD</span>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('Item') }}</th>
                                <th class="num">{{ __('Ordered') }}</th>
                                <th class="num">{{ __('Received') }}</th>
                                <th class="num">{{ __('Unit price') }}</th>
                                <th class="num">{{ __('Value') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        @forelse ($order->lines as $line)
                            @php($pack = $packSizes->get($line->item_id))
                            <tbody x-data="{ editing: false, closing: false }">
                                <tr>
                                    <td>
                                        <a class="link font-mono" href="{{ route('items.show', $line->item) }}">{{ $line->item->sku }}</a>
                                        <span class="text-gray-700">{{ $line->item->name }}</span>
                                        @if ($pack && bccomp($pack, '1', 3) !== 0)
                                            <span class="block text-xs text-gray-500">{{ __('Supplier pack: :n :uom', ['n' => Format::qty($pack), 'uom' => $line->item->uom]) }}</span>
                                        @endif
                                        @if ($line->is_closed)
                                            <span class="badge-gray">{{ __('Closed short') }}</span>
                                        @endif
                                    </td>
                                    <td class="num" x-show="!editing">{{ Format::qty($line->qty_ordered) }} {{ $line->item->uom }}</td>
                                    <td class="num" x-show="!editing">
                                        <span @class(['text-amber-700 font-semibold' => bccomp($line->qty_received, $line->qty_ordered, 3) > 0])>{{ Format::qty($line->qty_received) }}</span>
                                    </td>
                                    <td class="num" x-show="!editing">{{ Format::money($line->unit_price) }}</td>
                                    <td class="num" x-show="!editing">{{ Format::money($line->value()) }}</td>

                                    @if ($canEdit && $draft)
                                        <td colspan="4" x-show="editing" x-cloak>
                                            <form method="POST" action="{{ route('purchase-order-lines.update', $line) }}" class="flex flex-wrap items-center justify-end gap-2">
                                                @csrf @method('PUT')
                                                <input name="qty_ordered" value="{{ Format::qty($line->qty_ordered) }}" inputmode="decimal" class="form-input w-24 text-right" aria-label="{{ __('Quantity') }}">
                                                <input name="unit_price" value="{{ $line->unit_price }}" inputmode="decimal" class="form-input w-28 text-right" aria-label="{{ __('Unit price') }}">
                                                <button class="btn-primary btn-sm">{{ __('Save') }}</button>
                                                <button type="button" class="btn-secondary btn-sm" @click="editing = false">{{ __('Cancel') }}</button>
                                            </form>
                                        </td>
                                        <td class="text-right whitespace-nowrap" x-show="!editing">
                                            <button type="button" class="link text-sm" @click="editing = true">{{ __('Edit') }}</button>
                                            <form method="POST" action="{{ route('purchase-order-lines.destroy', $line) }}" class="inline">
                                                @csrf @method('DELETE')
                                                <button class="btn-link-danger ms-2">{{ __('Remove') }}</button>
                                            </form>
                                        </td>
                                    @elseif ($canEdit && $receiving && ! $line->isFulfilled())
                                        <td class="text-right whitespace-nowrap">
                                            <button type="button" class="link text-sm" x-show="!closing" @click="closing = true">{{ __('Close short…') }}</button>
                                        </td>
                                    @else
                                        <td></td>
                                    @endif
                                </tr>
                                @if ($canEdit && $receiving && ! $line->isFulfilled())
                                    <tr x-show="closing" x-cloak>
                                        <td colspan="6" class="bg-gray-50">
                                            <form method="POST" action="{{ route('purchase-order-lines.close-short', $line) }}" class="flex flex-wrap items-end gap-2">
                                                @csrf
                                                <x-field name="reason" :label="__(':qty :uom will not be delivered because…', ['qty' => Format::qty($line->outstanding()), 'uom' => $line->item->uom])" class="grow">
                                                    <input name="reason" required maxlength="500" class="form-input" placeholder="{{ __('e.g. discontinued by the supplier') }}">
                                                </x-field>
                                                <button class="btn-secondary">{{ __('Close line short') }}</button>
                                                <button type="button" class="btn-secondary" @click="closing = false">{{ __('Keep open') }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        @empty
                            <tbody><tr><td colspan="6" class="py-6 text-center text-gray-500">{{ __('No lines yet.') }}</td></tr></tbody>
                        @endforelse
                    </table>
                </div>

                @if ($canEdit && $draft)
                    <form method="POST" action="{{ route('purchase-orders.lines.store', $order) }}" class="card-body border-t border-gray-100 grid gap-3 sm:grid-cols-12 items-start">
                        @csrf
                        <div class="sm:col-span-6">
                            <x-field name="item_id" :label="__('Item')" required>
                                <x-item-picker name="item_id" required />
                            </x-field>
                        </div>
                        <div class="sm:col-span-2">
                            <x-field name="qty_ordered" :label="__('Quantity')" required>
                                <x-input name="qty_ordered" inputmode="decimal" class="text-right" required />
                            </x-field>
                        </div>
                        <div class="sm:col-span-3">
                            <x-field name="unit_price" :label="__('Unit price (USD)')" :hint="__('Empty: the supplier’s last price.')">
                                <x-input name="unit_price" inputmode="decimal" class="text-right" />
                            </x-field>
                        </div>
                        <div class="sm:col-span-1 sm:pt-6">
                            <button class="btn-primary w-full">{{ __('Add') }}</button>
                        </div>
                    </form>
                @endif
            </section>

            {{-- Details: dates are set by the steps and may be corrected (SPEC 5.3.8) --}}
            <section class="card card-body" x-data="{ editing: false }">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="card-title">{{ __('Details') }}</h3>
                    @if ($canEdit)
                        <button type="button" class="link text-sm" x-show="!editing" @click="editing = true">{{ __('Edit') }}</button>
                    @endif
                </div>

                <dl class="dl" x-show="!editing">
                    <dt>{{ __('Created') }}</dt><dd>{{ Format::datetime($order->created_at) }} · {{ $order->creator->name }}</dd>
                    <dt>{{ __('Ordered') }}</dt><dd>{{ Format::date($order->ordered_at) ?: '—' }}</dd>
                    <dt>{{ __('Confirmed') }}</dt><dd>{{ Format::date($order->confirmed_at) ?: '—' }}</dd>
                    <dt>{{ __('ETA') }}</dt>
                    <dd @class(['text-red-700 font-semibold' => $overdue])>{{ Format::date($order->eta) ?: '—' }} @if ($overdue)<span class="badge-red ms-1">{{ __('Late') }}</span>@endif</dd>
                    <dt>{{ __('Shipped') }}</dt><dd>{{ Format::date($order->shipped_at) ?: '—' }}</dd>
                    <dt>{{ __('Tracking') }}</dt>
                    <dd class="break-all">
                        @if ($trackingIsUrl)
                            <a class="link" href="{{ $order->tracking_ref }}" target="_blank" rel="noopener noreferrer">{{ $order->tracking_ref }}</a>
                        @else
                            {{ $order->tracking_ref ?? '—' }}
                        @endif
                    </dd>
                    <dt>{{ __('Closed') }}</dt><dd>{{ Format::date($order->closed_at) ?: '—' }}</dd>
                </dl>

                @if ($canEdit)
                    <form method="POST" action="{{ route('purchase-orders.update', $order) }}" class="space-y-3 text-sm" x-show="editing" x-cloak>
                        @csrf @method('PUT')
                        @foreach (['ordered_at' => __('Ordered'), 'confirmed_at' => __('Confirmed'), 'eta' => __('ETA'), 'shipped_at' => __('Shipped'), 'closed_at' => __('Closed')] as $field => $label)
                            <x-field :name="$field" :label="$label">
                                <x-input type="date" :name="$field" :value="$order->{$field}?->toDateString()" />
                            </x-field>
                        @endforeach
                        <x-field name="tracking_ref" :label="__('Tracking')">
                            <x-input name="tracking_ref" :value="$order->tracking_ref" maxlength="200" />
                        </x-field>
                        <x-field name="notes" :label="__('Notes')">
                            <x-textarea name="notes" :value="$order->notes" rows="4" />
                        </x-field>
                        <div class="flex gap-2">
                            <button class="btn-primary btn-sm">{{ __('Save') }}</button>
                            <button type="button" class="btn-secondary btn-sm" @click="editing = false">{{ __('Cancel') }}</button>
                        </div>
                    </form>
                @endif

                @if ($order->notes)
                    <div class="mt-4 border-t border-gray-100 pt-3" x-show="!editing">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Notes') }}</h4>
                        <p class="mt-1 text-sm text-gray-700 whitespace-pre-line">{{ $order->notes }}</p>
                    </div>
                @endif
            </section>
        </div>

        <section class="card" id="history">
            <div class="card-body pb-2"><h3 class="card-title">{{ __('Receipts') }}</h3></div>
            @include('stock._ledger', ['transactions' => $receipts, 'showItem' => true])
        </section>
    </x-page>
</x-app-layout>
