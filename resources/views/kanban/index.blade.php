@php use App\Support\Format; @endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Two-bin items')" :subtitle="($site ? $site->code.' · '.$site->name.' · ' : __('All sites').' · ').__('Two bins on the shelf: when the first is empty, reorder; the second covers the delivery time.')">
            <x-export-link />
            @if ($site && auth()->user()->can('setLevels', [App\Models\Stock::class, $site]))
                <a href="{{ route('stock.levels', ['site' => $site->id, 'kanban' => 1]) }}" class="btn-secondary btn-sm">{{ __('Two-bin settings') }}</a>
            @endif
        </x-page-header>
    </x-slot>

    <x-page>
        <div class="card table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Item') }}</th>
                        @unless ($site)<th>{{ __('Site') }}</th>@endunless
                        <th>{{ __('Location') }}</th>
                        <th class="num">{{ __('In stock') }}</th>
                        <th class="num">{{ __('Per bin') }}</th>
                        <th class="num">{{ __('Bins left') }}</th>
                        <th class="num">{{ __('On order') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stocks as $stock)
                        @php($ordered = $onOrder[$stock->item_id][$stock->site_id] ?? null)
                        <tr>
                            <td>
                                @if ($stock->needsReplenishment())
                                    <span class="badge-amber">{{ __('Refill') }}</span>
                                @else
                                    <span class="badge-green">{{ __('OK') }}</span>
                                @endif
                            </td>
                            <td>
                                <a class="link font-mono" href="{{ route('items.show', $stock->item) }}">{{ $stock->item->sku }}</a>
                                <span class="text-gray-700">{{ $stock->item->name }}</span>
                            </td>
                            @unless ($site)<td>{{ $stock->site->code }}</td>@endunless
                            <td class="font-mono text-gray-600">{{ $stock->bin }}</td>
                            <td class="num font-semibold">{{ Format::qty($stock->qty) }} {{ $stock->item->uom }}</td>
                            <td class="num text-gray-600">{{ Format::qty($stock->bin_qty) }}</td>
                            <td class="num">{{ $bins($stock) }}</td>
                            <td class="num text-indigo-700">{{ $ordered ? Format::qty($ordered) : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-6 text-center text-gray-500">{{ __('No two-bin items here yet. Mark low-value, regularly used items as two-bin in Min levels & locations.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-page>
</x-app-layout>
