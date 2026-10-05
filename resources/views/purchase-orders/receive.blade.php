@php use App\Support\Format; @endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Receive goods · :number', ['number' => $order->number])"
                       :subtitle="$order->supplier->name.' → '.$order->site->code.' · '.$order->site->name" />
    </x-slot>

    <x-page>
        @error('lines')
            <div class="rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</div>
        @enderror

        <form method="POST" action="{{ route('purchase-orders.receive.store', $order) }}" class="card">
            @csrf
            <div class="card-body pb-2">
                <p class="text-sm text-gray-600">{{ __('Quantities default to what is still outstanding. Change them to what actually arrived; leave a line empty or 0 if nothing came for it.') }}</p>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Item') }}</th>
                            <th class="num">{{ __('Ordered') }}</th>
                            <th class="num">{{ __('Already received') }}</th>
                            <th class="num">{{ __('Outstanding') }}</th>
                            <th class="num">{{ __('Received now') }}</th>
                        </tr>
                    </thead>
                    @forelse ($lines as $line)
                        @php
                            $outstanding = $line->outstanding();
                            $pack = $packSizes->get($line->item_id);
                        @endphp
                        <tbody x-data="{ qty: @js(old('lines.'.$line->id, Format::qty($outstanding))), outstanding: {{ $outstanding }} }">
                            <tr>
                                <td>
                                    <span class="font-mono">{{ $line->item->sku }}</span>
                                    <span class="text-gray-700">{{ $line->item->name }}</span>
                                    @if ($pack && bccomp($pack, '1', 3) !== 0)
                                        <span class="block text-xs text-gray-500">{{ __('Supplier pack: :n :uom', ['n' => Format::qty($pack), 'uom' => $line->item->uom]) }}</span>
                                    @endif
                                </td>
                                <td class="num">{{ Format::qty($line->qty_ordered) }}</td>
                                <td class="num">{{ Format::qty($line->qty_received) }}</td>
                                <td class="num font-semibold">{{ Format::qty($outstanding) }} {{ $line->item->uom }}</td>
                                <td class="num">
                                    <input name="lines[{{ $line->id }}]" x-model="qty" inputmode="decimal" autocomplete="off"
                                           aria-label="{{ __('Quantity received now for :sku', ['sku' => $line->item->sku]) }}"
                                           class="form-input w-28 text-right" :class="parseFloat(qty) > outstanding ? 'border-amber-500 bg-amber-50' : ''">
                                </td>
                            </tr>
                            <tr x-show="parseFloat(qty) > outstanding" x-cloak>
                                <td colspan="5" class="bg-amber-50 text-sm text-amber-800">
                                    {{ __('More than ordered. This is allowed, but it usually means a pack-size mix-up: check whether packs were counted instead of units.') }}
                                    @if ($pack && bccomp($pack, '1', 3) !== 0)
                                        {{ __('One pack from this supplier is :n :uom.', ['n' => Format::qty($pack), 'uom' => $line->item->uom]) }}
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    @empty
                        <tbody><tr><td colspan="5" class="py-6 text-center text-gray-500">{{ __('Nothing is outstanding on this order.') }}</td></tr></tbody>
                    @endforelse
                </table>
            </div>

            <div class="card-body border-t border-gray-100 flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-gray-500">{{ __('Stock at :site increases immediately, valued at the order price.', ['site' => $order->site->code]) }}</p>
                <div class="flex gap-2">
                    <a href="{{ route('purchase-orders.show', $order) }}" class="btn-secondary">{{ __('Cancel') }}</a>
                    <button class="btn-primary" @disabled($lines->isEmpty())>{{ __('Record receipt') }}</button>
                </div>
            </div>
        </form>
    </x-page>
</x-app-layout>
