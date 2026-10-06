@php
    use App\Support\Format;

    $submitted = old('lines') !== null;
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Quick order')" :subtitle="__('Check the supplier, quantity and price for each item. One draft order is created per site and supplier; nothing is sent until you send each draft.')" />
    </x-slot>

    <x-page>
        <form method="POST" action="{{ route('purchase-orders.quick.store') }}" class="card overflow-hidden">
            @csrf

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="w-10"><span class="sr-only">{{ __('Order') }}</span></th>
                            <th>{{ __('Item') }}</th>
                            <th class="num">{{ __('In stock') }}</th>
                            <th>{{ __('Supplier') }}</th>
                            <th class="num">{{ __('Quantity') }}</th>
                            <th class="num">{{ __('Unit price') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php
                                $stock = $row['stock'];
                                $id = $stock->id;
                                $checked = $submitted ? (bool) old("lines.$id.include") : $row['include'];
                                $linked = $row['suppliers']->pluck('name', 'id')->all();
                                $options = $linked === [] ? $suppliers : [
                                    __('Supplies this item') => $linked,
                                    __('Other suppliers') => array_diff_key($suppliers, $linked),
                                ];
                                $prices = collect($row['prices'])->map(fn ($p) => $p === null ? null : Format::money($p))->all();
                                $packs = collect($row['packs'])->map(fn ($p) => Format::qty($p))->all();
                            @endphp
                            <tr class="align-top" x-data="{ supplier: '{{ old("lines.$id.supplier_id", $row['supplier_id']) }}', prices: @js($prices), packs: @js($packs) }">
                                <td>
                                    <input type="checkbox" name="lines[{{ $id }}][include]" value="1" @checked($checked)
                                           class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                           aria-label="{{ __('Order :sku', ['sku' => $stock->item->sku]) }}">
                                </td>
                                <td class="min-w-[14rem]">
                                    <span class="font-mono text-sm">{{ $stock->item->sku }}</span>
                                    <span class="text-sm text-gray-900">{{ $stock->item->name }}</span>
                                    <span class="block text-xs text-gray-500">
                                        {{ implode(' · ', array_filter([
                                            $siteCodes ? $stock->site->code : null,
                                            $stock->is_kanban ? __('two-bin, :n per bin', ['n' => Format::qty($stock->bin_qty)]) : __('min :n', ['n' => Format::qty($stock->min_level)]),
                                            $stock->bin ? __('location :b', ['b' => $stock->bin]) : null,
                                        ])) }}
                                    </span>
                                    @foreach ($row['in_progress'] as $order)
                                        <span class="mt-1 flex items-center gap-1 text-xs text-amber-800">
                                            <x-status-icon severity="warning" />
                                            {{ __('Already on') }}
                                            <a class="link" href="{{ route('purchase-orders.show', $order['id']) }}">{{ $order['number'] }}</a>
                                            ({{ strtolower($order['status']->label()) }}, {{ Format::qty($order['qty']) }} {{ $stock->item->uom }})
                                        </span>
                                    @endforeach
                                </td>
                                <td class="num">{{ Format::qty($stock->qty) }} {{ $stock->item->uom }}</td>
                                <td class="min-w-[12rem]">
                                    <x-select name="lines[{{ $id }}][supplier_id]" id="supplier-{{ $id }}" :options="$options" :value="$row['supplier_id']"
                                              :placeholder="__('— Choose —')" x-model="supplier" aria-label="{{ __('Supplier for :sku', ['sku' => $stock->item->sku]) }}" />
                                    <x-input-error :messages="$errors->get('lines.'.$id.'.supplier_id')" class="mt-1" />
                                </td>
                                <td class="num">
                                    <span class="inline-flex items-center gap-1">
                                        <input name="lines[{{ $id }}][qty]" value="{{ old("lines.$id.qty", Format::qty($row['qty'])) }}" inputmode="decimal"
                                               class="form-input w-24 text-right" aria-label="{{ __('Quantity of :sku', ['sku' => $stock->item->sku]) }}">
                                        <span class="text-xs text-gray-500">{{ $stock->item->uom }}</span>
                                    </span>
                                    <span class="block text-xs text-gray-500" x-show="packs[supplier] && packs[supplier] !== '1'" x-cloak>
                                        {{ __('pack of') }} <span x-text="packs[supplier]"></span>
                                    </span>
                                    <x-input-error :messages="$errors->get('lines.'.$id.'.qty')" class="mt-1 text-left" />
                                </td>
                                <td class="num">
                                    <input name="lines[{{ $id }}][unit_price]" value="{{ old("lines.$id.unit_price") }}" inputmode="decimal"
                                           class="form-input w-28 text-right" :placeholder="prices[supplier] ?? '{{ __('enter price') }}'"
                                           aria-label="{{ __('Unit price of :sku', ['sku' => $stock->item->sku]) }}">
                                    <span class="block text-xs text-gray-500" x-show="prices[supplier]" x-cloak>{{ __('blank = last price') }}</span>
                                    <x-input-error :messages="$errors->get('lines.'.$id.'.unit_price')" class="mt-1 text-left" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-body flex flex-wrap items-center justify-between gap-3 border-t border-gray-100">
                <p class="max-w-2xl text-xs text-gray-500">
                    {{ __('Suggested quantity: one bin for a two-bin item, otherwise enough to reach twice the minimum, less what is already on order, rounded up to whole packs. Items already on an order start unticked.') }}
                </p>
                <div class="flex gap-2">
                    <button class="btn-primary">{{ __('Create draft orders') }}</button>
                    <a href="{{ route('dashboard') }}" class="btn-secondary">{{ __('Cancel') }}</a>
                </div>
            </div>
        </form>
    </x-page>
</x-app-layout>
