<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$item->sku.' · '.$item->name" :subtitle="$item->category->fullName()">
            @unless ($item->is_active)
                <span class="badge-gray">{{ __('Inactive') }}</span>
            @endunless
            @can('update', $item)
                <a href="{{ route('items.edit', $item) }}" class="btn-secondary">{{ __('Edit') }}</a>
            @endcan
        </x-page-header>
    </x-slot>

    <x-page>
        <div class="grid gap-6 lg:grid-cols-3">
            <section class="card card-body lg:col-span-1">
                <h3 class="card-title mb-4">{{ __('Master data') }}</h3>
                <dl class="dl">
                    <dt>{{ __('SKU') }}</dt><dd class="font-mono">{{ $item->sku }}</dd>
                    <dt>{{ __('Unit') }}</dt><dd>{{ $item->uom }}</dd>
                    <dt>{{ __('Criticality') }}</dt><dd>{{ $item->criticality?->value ?? '—' }}</dd>
                    <dt>{{ __('Manufacturer') }}</dt><dd>{{ $item->manufacturer ?? '—' }}</dd>
                    <dt>{{ __('MPN') }}</dt><dd>{{ $item->mpn ?? '—' }}</dd>
                    <dt>{{ __('Drawing') }}</dt><dd>{{ $item->drawing_no ?? '—' }}</dd>
                </dl>
                @if ($item->description)
                    <p class="mt-4 text-sm text-gray-700 whitespace-pre-line">{{ $item->description }}</p>
                @endif
            </section>

            <section class="card lg:col-span-2">
                <div class="card-body pb-2"><h3 class="card-title">{{ __('Stock by site') }}</h3></div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('Site') }}</th>
                                <th class="num">{{ __('Quantity') }}</th>
                                <th class="num">{{ __('Avg cost') }}</th>
                                <th class="num">{{ __('Min level') }}</th>
                                <th>{{ __('Bin') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sites as $site)
                                @php($stock = $stocksBySite->get($site->id))
                                <tr @class(['bg-indigo-50/50' => $currentSite->id() === $site->id])>
                                    <td>{{ $site->code }} · {{ $site->name }}</td>
                                    <td class="num">{{ \App\Support\Format::qty($stock?->qty ?? 0) }} {{ $item->uom }}</td>
                                    <td class="num">{{ $stock ? \App\Support\Format::money($stock->avg_cost) : '—' }}</td>
                                    <td class="num">{{ $stock && $stock->min_level > 0 ? \App\Support\Format::qty($stock->min_level) : '—' }}</td>
                                    <td>{{ $stock?->bin ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="card">
                <div class="card-body pb-2"><h3 class="card-title">{{ __('Suppliers') }}</h3></div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('Supplier') }}</th>
                                <th>{{ __('Their SKU') }}</th>
                                <th class="num">{{ __('Last price') }}</th>
                                <th class="num">{{ __('Pack') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($item->supplierItems as $supplierItem)
                                <tr>
                                    <td>
                                        @can('view', $supplierItem->supplier)
                                            <a class="link" href="{{ route('suppliers.show', $supplierItem->supplier) }}">{{ $supplierItem->supplier->name }}</a>
                                        @else
                                            {{ $supplierItem->supplier->name }}
                                        @endcan
                                    </td>
                                    <td class="font-mono">{{ $supplierItem->supplier_sku ?? '—' }}</td>
                                    <td class="num">{{ $supplierItem->last_price !== null ? \App\Support\Format::money($supplierItem->last_price) : '—' }}</td>
                                    <td class="num">{{ \App\Support\Format::qty($supplierItem->pack_size) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-gray-500">{{ __('No supplier linked yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card">
                <div class="card-body pb-2"><h3 class="card-title">{{ __('Used on machine types') }}</h3></div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('Machine type') }}</th>
                                <th>{{ __('Reference') }}</th>
                                <th class="num">{{ __('Qty / machine') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($item->machineTypes as $machineType)
                                <tr>
                                    <td>
                                        @can('view', $machineType)
                                            <a class="link" href="{{ route('machine-types.show', $machineType) }}">{{ $machineType->name }}</a>
                                        @else
                                            {{ $machineType->name }}
                                        @endcan
                                    </td>
                                    <td>{{ $machineType->pivot->reference ?? '—' }}</td>
                                    <td class="num">{{ $machineType->pivot->qty_per_machine !== null ? \App\Support\Format::qty($machineType->pivot->qty_per_machine) : '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-gray-500">{{ __('Not on any parts list.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </x-page>
</x-app-layout>
