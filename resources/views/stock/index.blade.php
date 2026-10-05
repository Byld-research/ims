<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Stock')" :subtitle="__('The catalogue with quantities at every site.')">
            <x-export-link />
            <a href="{{ route('categories.index') }}" class="btn-secondary btn-sm">{{ __('Categories') }}</a>
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
                <select name="category" id="category" class="form-input mt-1">
                    <option value="">{{ __('All categories') }}</option>
                    @foreach ($categories as $group => $children)
                        @if (is_array($children))
                            <optgroup label="{{ $group }}">
                                @foreach ($children as $id => $name)
                                    <option value="{{ $id }}" @selected(($filters['category'] ?? null) == $id)>{{ $name }}</option>
                                @endforeach
                            </optgroup>
                        @else
                            <option value="{{ $group }}" @selected(($filters['category'] ?? null) == $group)>{{ $children }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div>
                <label for="criticality" class="block text-xs font-medium text-gray-600">{{ __('Criticality') }}</label>
                <select name="criticality" id="criticality" class="form-input mt-1">
                    <option value="">{{ __('Any') }}</option>
                    @foreach (['A', 'B', 'C'] as $class)
                        <option value="{{ $class }}" @selected(($filters['criticality'] ?? null) === $class)>{{ $class }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center justify-between gap-2">
                <label class="inline-flex items-center gap-1.5 text-sm text-gray-700">
                    <input type="checkbox" name="inactive" value="1" @checked($filters['inactive'] ?? false) class="form-checkbox">
                    {{ __('Inactive') }}
                </label>
                <button class="btn-primary btn-sm">{{ __('Filter') }}</button>
            </div>
        </form>

        <div class="card table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('SKU') }}</th>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('Crit.') }}</th>
                        @foreach ($sites as $site)
                            <th class="num {{ $currentSiteId === $site->id ? 'text-indigo-700' : '' }}">{{ $site->code }}</th>
                        @endforeach
                        <th>{{ __('Unit') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        @php($stocks = $item->stocks->keyBy('site_id'))
                        <tr>
                            <td class="font-mono whitespace-nowrap"><a class="link" href="{{ route('items.show', $item) }}">{{ $item->sku }}</a></td>
                            <td>
                                {{ $item->name }}
                                @unless ($item->is_active)
                                    <span class="badge-gray ms-1">{{ __('Inactive') }}</span>
                                @endunless
                            </td>
                            <td class="text-gray-600">{{ $item->category->name }}</td>
                            <td>{{ $item->criticality?->value }}</td>
                            @foreach ($sites as $site)
                                <td class="num {{ $currentSiteId === $site->id ? 'font-semibold' : 'text-gray-600' }}">
                                    {{ \App\Support\Format::qty($stocks->get($site->id)?->qty ?? 0) }}
                                </td>
                            @endforeach
                            <td class="text-gray-600">{{ $item->uom }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 5 + $sites->count() }}" class="py-6 text-center text-gray-500">
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
