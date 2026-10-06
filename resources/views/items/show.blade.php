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
                                <th class="num">{{ __('Value') }}</th>
                                <th class="num">{{ __('On order') }}</th>
                                <th class="num">{{ __('Min level') }}</th>
                                <th>{{ __('Location') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sites as $site)
                                @php($stock = $stocksBySite->get($site->id))
                                <tr @class(['bg-indigo-50/50' => $currentSite->id() === $site->id])>
                                    <td>{{ $site->code }} · {{ $site->name }}</td>
                                    <td class="num">{{ \App\Support\Format::qty($stock?->qty ?? 0) }} {{ $item->uom }}</td>
                                    <td class="num">{{ $stock ? \App\Support\Format::money($stock->avg_cost) : '—' }}</td>
                                    <td class="num">{{ $stock ? \App\Support\Format::money($stock->value()) : '—' }}</td>
                                    <td class="num text-indigo-700">{{ isset($onOrder[$site->id]) ? \App\Support\Format::qty($onOrder[$site->id]) : '—' }}</td>
                                    <td class="num">
                                        @if ($stock?->is_kanban)
                                            {{ __('two-bin, :q per bin', ['q' => \App\Support\Format::qty($stock->bin_qty)]) }}
                                        @else
                                            {{ $stock && bccomp($stock->min_level, '0', 3) > 0 ? \App\Support\Format::qty($stock->min_level) : '—' }}
                                        @endif
                                        @if ($stock?->needsReplenishment())
                                            <span class="badge-red ms-1">{{ __('Low') }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $stock?->bin ?? '—' }}</td>
                                    <td class="text-right whitespace-nowrap">
                                        @can('adjust', [App\Models\Stock::class, $site])
                                            <a class="link text-sm" href="{{ route('adjustments.create', ['item' => $item->id, 'site' => $site->id]) }}">{{ __('Adjust') }}</a>
                                        @endcan
                                    </td>
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
                                <th>{{ __('Revision') }}</th>
                                <th>{{ __('Reference') }}</th>
                                <th class="num">{{ __('Qty / machine') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($item->machineTypes as $machineType)
                                <tr>
                                    <td>
                                        @can('view', $machineType)
                                            <a class="link" href="{{ route('machine-types.show', $machineType) }}">{{ $machineType->label() }}</a>
                                        @else
                                            {{ $machineType->label() }}
                                        @endcan
                                    </td>
                                    <td>{{ $machineType->pivot->revision ?? __('All') }}</td>
                                    <td>{{ $machineType->pivot->reference ?? '—' }}</td>
                                    <td class="num">{{ $machineType->pivot->qty_per_machine !== null ? \App\Support\Format::qty($machineType->pivot->qty_per_machine) : '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-gray-500">{{ __('Not on any parts list.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        @if ($openOrders->isNotEmpty())
            <section class="card">
                <div class="card-body pb-2"><h3 class="card-title">{{ __('On open orders') }}</h3></div>
                <div class="table-wrap">
                    <table class="table">
                        <tbody>
                            @foreach ($openOrders as $line)
                                <tr>
                                    <td class="font-mono"><a class="link" href="{{ route('purchase-orders.show', $line->purchaseOrder) }}">{{ $line->purchaseOrder->number }}</a></td>
                                    <td>{{ $line->purchaseOrder->supplier->name }} → {{ $line->purchaseOrder->site->code }}</td>
                                    <td><x-po-status :status="$line->purchaseOrder->status" /></td>
                                    <td>{{ $line->purchaseOrder->eta ? __('ETA :date', ['date' => \App\Support\Format::date($line->purchaseOrder->eta)]) : '' }}</td>
                                    <td class="num">{{ __(':qty outstanding', ['qty' => \App\Support\Format::qty($line->outstanding())]) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="card" id="history">
            <div class="card-body pb-2 flex flex-wrap items-center justify-between gap-3">
                <h3 class="card-title">{{ __('Movement history') }}</h3>
                <form method="GET" action="{{ route('items.show', $item) }}#history" class="flex items-center gap-2 text-sm">
                    <x-export-link />
                    <label for="history_site" class="text-gray-600">{{ __('Site') }}</label>
                    <select name="history_site" id="history_site" class="form-input py-1 w-40" onchange="this.form.submit()">
                        <option value="">{{ __('All sites') }}</option>
                        @foreach ($sites as $site)
                            <option value="{{ $site->id }}" @selected($historySite === $site->id)>{{ $site->code }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
            @include('stock._ledger', ['transactions' => $transactions, 'showItem' => false, 'uom' => $item->uom])
        </section>
    </x-page>
</x-app-layout>
