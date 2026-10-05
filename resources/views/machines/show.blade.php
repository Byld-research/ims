<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$machine->sku.' · '.$machine->displayName()"
                       :subtitle="$machine->machineType->label().' · '.$machine->site->code.' '.$machine->site->name">
            <x-active-badge :active="$machine->is_active" />
            @can('update', $machine)
                <a href="{{ route('machines.edit', $machine) }}" class="btn-secondary">{{ __('Edit') }}</a>
            @endcan
        </x-page-header>
    </x-slot>

    <x-page>
        <section class="card">
            <div class="card-body pb-2">
                <h3 class="card-title">{{ __('Parts list for revision :revision', ['revision' => $machine->revision]) }}</h3>
                <p class="text-sm text-gray-500">
                    @can('view', $machine->machineType)
                        <a class="link" href="{{ route('machine-types.show', $machine->machineType) }}">{{ $machine->machineType->label() }}</a>
                    @else
                        {{ $machine->machineType->label() }}
                    @endcan
                    · {{ __('stock shown is at :site', ['site' => $machine->site->code]) }}
                </p>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Item') }}</th>
                            <th>{{ __('Applies to') }}</th>
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
                                <td>
                                    @if ($line->revision)
                                        <span class="badge-indigo">{{ __('Rev. :r only', ['r' => $line->revision]) }}</span>
                                    @else
                                        <span class="text-gray-500">{{ __('All revisions') }}</span>
                                    @endif
                                </td>
                                <td>{{ $line->reference ?? '—' }}</td>
                                <td class="num">{{ $line->qty_per_machine !== null ? \App\Support\Format::qty($line->qty_per_machine) : '—' }}</td>
                                <td class="num {{ ($stock?->qty ?? 0) > 0 ? 'font-semibold' : 'text-red-600' }}">
                                    {{ \App\Support\Format::qty($stock?->qty ?? 0) }} {{ $line->item->uom }}
                                </td>
                                <td>{{ $stock?->bin ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-gray-500">{{ __('No parts are listed for this type and revision yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card card-body">
            <h3 class="card-title">{{ __('Consumption history') }}</h3>
            <p class="mt-1 text-sm text-gray-500">{{ __('Stock issued to this machine will be listed here once stock movements are recorded.') }}</p>
        </section>
    </x-page>
</x-app-layout>
