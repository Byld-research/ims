<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$workCenter->code.' · '.$workCenter->name" :subtitle="$workCenter->site->code.' · '.$workCenter->site->name">
            <x-active-badge :active="$workCenter->is_active" />
            @can('update', $workCenter)
                <a href="{{ route('work-centers.edit', $workCenter) }}" class="btn-secondary">{{ __('Edit') }}</a>
            @endcan
        </x-page-header>
    </x-slot>

    <x-page>
        <section class="card">
            <div class="card-body pb-2">
                <h3 class="card-title">{{ __('Parts list') }}</h3>
                <p class="text-sm text-gray-500">
                    @if ($workCenter->machineType)
                        @can('view', $workCenter->machineType)
                            <a class="link" href="{{ route('machine-types.show', $workCenter->machineType) }}">{{ $workCenter->machineType->name }}</a>
                        @else
                            {{ $workCenter->machineType->name }}
                        @endcan
                        · {{ __('stock shown is at :site', ['site' => $workCenter->site->code]) }}
                    @else
                        {{ __('No machine type assigned, so there is no parts list.') }}
                    @endif
                </p>
            </div>
            @if ($workCenter->machineType)
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('Item') }}</th>
                                <th>{{ __('Reference') }}</th>
                                <th class="num">{{ __('Qty / machine') }}</th>
                                <th class="num">{{ __('In stock') }}</th>
                                <th>{{ __('Bin') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($partsList as $line)
                                @php($stock = $stocks->get($line->item_id))
                                <tr>
                                    <td>
                                        <a class="link font-mono" href="{{ route('items.show', $line->item) }}">{{ $line->item->sku }}</a>
                                        <span class="text-gray-700">{{ $line->item->name }}</span>
                                        @if ($line->is_consumable)<span class="badge-amber ms-1">{{ __('Consumable') }}</span>@endif
                                    </td>
                                    <td>{{ $line->reference ?? '—' }}</td>
                                    <td class="num">{{ $line->qty_per_machine !== null ? \App\Support\Format::qty($line->qty_per_machine) : '—' }}</td>
                                    <td class="num {{ ($stock?->qty ?? 0) > 0 ? 'font-semibold' : 'text-red-600' }}">
                                        {{ \App\Support\Format::qty($stock?->qty ?? 0) }} {{ $line->item->uom }}
                                    </td>
                                    <td>{{ $stock?->bin ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-gray-500">{{ __('The parts list for this machine type is empty.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="card card-body">
            <h3 class="card-title">{{ __('Consumption history') }}</h3>
            <p class="mt-1 text-sm text-gray-500">{{ __('Issues to this work centre will be listed here once stock movements are recorded.') }}</p>
        </section>
    </x-page>
</x-app-layout>
