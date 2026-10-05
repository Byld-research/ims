<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Stock')" :subtitle="$site ? __('Quantities at every site; levels and value at :site.', ['site' => $site->code]) : __('Quantities at every site.')">
            <x-export-link />
            <a href="{{ route('categories.index') }}" class="btn-secondary btn-sm">{{ __('Categories') }}</a>
            @if ($site && auth()->user()->can('setLevels', [App\Models\Stock::class, $site]))
                <a href="{{ route('stock.levels', ['site' => $site->id]) }}" class="btn-secondary btn-sm">{{ __('Min levels & bins') }}</a>
            @endif
            @if ($site && auth()->user()->can('adjust', [App\Models\Stock::class, $site]))
                <a href="{{ route('adjustments.create') }}" class="btn-secondary btn-sm">{{ __('Adjust') }}</a>
            @endif
            @if ($site && auth()->user()->can('adjust', [App\Models\Stock::class, $site]))
                <a href="{{ route('transfers.create') }}" class="btn-secondary btn-sm">{{ __('Transfer in') }}</a>
            @endif
            @can('create', App\Models\Item::class)
                <a href="{{ route('items.create') }}" class="btn-primary">{{ __('New item') }}</a>
            @endcan
        </x-page-header>
    </x-slot>

    <x-page>
        <form method="GET" action="{{ route('stock.index') }}" class="card card-body grid gap-3 sm:grid-cols-6 items-end">
            <div class="sm:col-span-2">
                <label for="q" class="block text-xs font-medium text-gray-600">{{ __('Search') }}</label>
                <input type="search" name="q" id="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('SKU, name or MPN') }}" class="form-input mt-1">
            </div>
            <div class="sm:col-span-2">
                <label for="category" class="block text-xs font-medium text-gray-600">{{ __('Category') }}</label>
                <x-select name="category" :options="$categories" :value="$filters['category'] ?? null" :placeholder="__('All categories')" class="mt-1" />
            </div>
            <div>
                <label for="criticality" class="block text-xs font-medium text-gray-600">{{ __('Criticality') }}</label>
                <x-select name="criticality" :options="['A' => 'A', 'B' => 'B', 'C' => 'C']" :value="$filters['criticality'] ?? null" :placeholder="__('Any')" class="mt-1" />
            </div>
            <div class="space-y-1 text-sm text-gray-700">
                <label class="flex items-center gap-1.5"><input type="checkbox" name="below" value="1" @checked($filters['below'] ?? false) class="form-checkbox"> {{ __('Needs replenishment') }}</label>
                <label class="flex items-center gap-1.5"><input type="checkbox" name="kanban" value="1" @checked($filters['kanban'] ?? false) class="form-checkbox"> {{ __('Kanban only') }}</label>
                <label class="flex items-center gap-1.5"><input type="checkbox" name="inactive" value="1" @checked($filters['inactive'] ?? false) class="form-checkbox"> {{ __('Include inactive') }}</label>
            </div>
            <div class="sm:col-span-6 flex justify-end">
                <button class="btn-primary btn-sm">{{ __('Filter') }}</button>
            </div>
        </form>

        <div class="card table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('SKU') }}</th>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Crit.') }}</th>
                        @foreach ($sites as $each)
                            <th class="num {{ $currentSiteId === $each->id ? 'text-indigo-700' : '' }}">{{ $each->code }}</th>
                        @endforeach
                        <th>{{ __('Unit') }}</th>
                        @if ($site)
                            <th class="num">{{ __('Min') }}</th>
                            <th>{{ __('Bin') }}</th>
                            <th class="num">{{ __('Value') }}</th>
                            <th>{{ __('Status') }}</th>
                        @endif
                        <th class="num">{{ __('On order') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        @php
                            $stocks = $item->stocks->keyBy('site_id');
                            $here = $site ? $stocks->get($site->id) : null;
                        @endphp
                        <tr>
                            <td class="font-mono whitespace-nowrap"><a class="link" href="{{ route('items.show', $item) }}">{{ $item->sku }}</a></td>
                            <td>
                                {{ $item->name }}
                                <span class="block text-xs text-gray-500">{{ $item->category->name }}</span>
                                @unless ($item->is_active)
                                    <span class="badge-gray">{{ __('Inactive') }}</span>
                                @endunless
                            </td>
                            <td>{{ $item->criticality?->value }}</td>
                            @foreach ($sites as $each)
                                @php($stock = $stocks->get($each->id))
                                <td class="num {{ $currentSiteId === $each->id ? 'font-semibold' : 'text-gray-600' }}">
                                    {{ \App\Support\Format::qty($stock?->qty ?? 0) }}
                                    @if (! $site && $stock?->needsReplenishment())
                                        <span class="badge-red ms-1" title="{{ __('Needs replenishment') }}">!</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="text-gray-600">{{ $item->uom }}</td>
                            @if ($site)
                                <td class="num text-gray-600">
                                    @if ($here?->is_kanban)
                                        <span title="{{ __('Kanban: alert at one bin or less') }}">{{ __('bin') }} {{ \App\Support\Format::qty($here->bin_qty) }}</span>
                                    @elseif ($here && bccomp($here->min_level, '0', 3) > 0)
                                        {{ \App\Support\Format::qty($here->min_level) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-gray-600">{{ $here?->bin ?? '' }}</td>
                                <td class="num text-gray-600">{{ $here ? \App\Support\Format::money($here->value()) : '' }}</td>
                                <td>
                                    @if ($here?->needsReplenishment())
                                        @if ($here->is_kanban)
                                            <span class="badge-amber">{{ __('Refill') }}</span>
                                        @elseif (bccomp($here->qty, '0', 3) === 0)
                                            <span class="badge-red">{{ __('Out') }}</span>
                                        @else
                                            <span class="badge-red">{{ __('Low') }}</span>
                                        @endif
                                    @endif
                                </td>
                            @endif
                            @php($ordered = collect($onOrder[$item->id] ?? [])->reduce(fn ($sum, $q) => bcadd($sum, $q, 3), '0'))
                            <td class="num text-indigo-700">{{ bccomp($ordered, '0', 3) > 0 ? \App\Support\Format::qty($ordered) : '' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 5 + $sites->count() + ($site ? 4 : 0) }}" class="py-6 text-center text-gray-500">
                                {{ __('No items match.') }}
                                @can('create', App\Models\Item::class)
                                    <a class="link" href="{{ route('items.create') }}">{{ __('Create one') }}</a>
                                @endcan
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $items->links() }}
    </x-page>
</x-app-layout>
