<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$supplier->name">
            <x-active-badge :active="$supplier->is_active" />
            @can('update', $supplier)
                <a href="{{ route('suppliers.edit', $supplier) }}" class="btn-secondary">{{ __('Edit') }}</a>
            @endcan
        </x-page-header>
    </x-slot>

    <x-page>
        <section class="card card-body">
            <dl class="dl max-w-2xl">
                <dt>{{ __('Email') }}</dt><dd>{{ $supplier->contact_email ?? '—' }}</dd>
                <dt>{{ __('Phone') }}</dt><dd>{{ $supplier->contact_phone ?? '—' }}</dd>
                <dt>{{ __('Lead time') }}</dt><dd>{{ $supplier->lead_time_days !== null ? $supplier->lead_time_days.' '.__('days') : '—' }}</dd>
                @if ($supplier->notes)
                    <dt>{{ __('Notes') }}</dt><dd class="whitespace-pre-line">{{ $supplier->notes }}</dd>
                @endif
            </dl>
        </section>

        <section class="card">
            <div class="card-body pb-2">
                <h3 class="card-title">{{ __('Items supplied') }}</h3>
                <p class="text-sm text-gray-500">{{ __('The last price prefills new order lines. Pack size is for reference only; quantities are always in the item’s unit.') }}</p>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Item') }}</th>
                            <th>{{ __('Supplier SKU') }}</th>
                            <th class="num">{{ __('Last price') }}</th>
                            <th class="num">{{ __('Pack size') }}</th>
                            @can('update', $supplier)<th></th>@endcan
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($supplier->supplierItems as $link)
                            <tr x-data="{ editing: false }">
                                <td>
                                    <a class="link font-mono" href="{{ route('items.show', $link->item) }}">{{ $link->item->sku }}</a>
                                    <span class="text-gray-700">{{ $link->item->name }}</span>
                                </td>
                                <td class="font-mono" x-show="!editing">{{ $link->supplier_sku ?? '—' }}</td>
                                <td class="num" x-show="!editing">{{ $link->last_price !== null ? \App\Support\Format::money($link->last_price) : '—' }}</td>
                                <td class="num" x-show="!editing">{{ \App\Support\Format::qty($link->pack_size) }} {{ $link->item->uom }}</td>
                                @can('update', $supplier)
                                    <td colspan="3" x-show="editing" x-cloak>
                                        <form method="POST" action="{{ route('supplier-items.update', $link) }}" class="flex flex-wrap items-center gap-2">
                                            @csrf @method('PUT')
                                            <input name="supplier_sku" value="{{ $link->supplier_sku }}" placeholder="{{ __('Supplier SKU') }}" maxlength="80" class="form-input w-36">
                                            <input name="last_price" value="{{ $link->last_price }}" placeholder="{{ __('Price') }}" inputmode="decimal" class="form-input w-28 text-right">
                                            <input name="pack_size" value="{{ $link->pack_size }}" placeholder="{{ __('Pack') }}" inputmode="decimal" class="form-input w-20 text-right">
                                            <button class="btn-primary btn-sm">{{ __('Save') }}</button>
                                            <button type="button" class="btn-secondary btn-sm" @click="editing = false">{{ __('Cancel') }}</button>
                                        </form>
                                    </td>
                                    <td class="text-right whitespace-nowrap" x-show="!editing">
                                        <button type="button" class="link text-sm" @click="editing = true">{{ __('Edit') }}</button>
                                        <form method="POST" action="{{ route('supplier-items.destroy', $link) }}" class="inline"
                                              x-data @submit="if (!window.confirm(@js(__('Unlink :sku from this supplier?', ['sku' => $link->item->sku])))) $event.preventDefault()">
                                            @csrf @method('DELETE')
                                            <button class="btn-link-danger ms-2">{{ __('Unlink') }}</button>
                                        </form>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-gray-500">{{ __('No items linked yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @can('update', $supplier)
                <form method="POST" action="{{ route('suppliers.items.store', $supplier) }}" class="card-body border-t border-gray-100 grid gap-3 sm:grid-cols-12 items-start">
                    @csrf
                    <div class="sm:col-span-5">
                        <x-field name="item_id" :label="__('Item')" required>
                            <x-item-picker name="item_id" required />
                        </x-field>
                    </div>
                    <div class="sm:col-span-2">
                        <x-field name="supplier_sku" :label="__('Supplier SKU')">
                            <x-input name="supplier_sku" maxlength="80" />
                        </x-field>
                    </div>
                    <div class="sm:col-span-2">
                        <x-field name="last_price" :label="__('Last price (USD)')">
                            <x-input name="last_price" inputmode="decimal" class="text-right" />
                        </x-field>
                    </div>
                    <div class="sm:col-span-2">
                        <x-field name="pack_size" :label="__('Pack size')">
                            <x-input name="pack_size" value="1" inputmode="decimal" class="text-right" />
                        </x-field>
                    </div>
                    <div class="sm:col-span-1 sm:pt-6">
                        <button class="btn-primary w-full">{{ __('Add') }}</button>
                    </div>
                </form>
            @endcan
        </section>
    </x-page>
</x-app-layout>
