<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Min levels and bins · :site', ['site' => $site->code])"
                       :subtitle="__('A min level of 0 turns the alert off. Kanban items alert at one bin or less and ignore the min level.')">
            <a href="{{ route('stock.index') }}" class="btn-secondary btn-sm">{{ __('Back to stock') }}</a>
        </x-page-header>
    </x-slot>

    <x-page>
        <form method="GET" class="card card-body grid gap-3 sm:grid-cols-6 items-end">
            <input type="hidden" name="site" value="{{ $site->id }}">
            <div class="sm:col-span-2">
                <label for="q" class="block text-xs font-medium text-gray-600">{{ __('Search') }}</label>
                <input type="search" name="q" id="q" value="{{ $filters['q'] ?? '' }}" class="form-input mt-1" placeholder="{{ __('SKU or name') }}">
            </div>
            <div class="sm:col-span-2">
                <label for="category" class="block text-xs font-medium text-gray-600">{{ __('Category') }}</label>
                <x-select name="category" :options="$categories" :value="$filters['category'] ?? null" :placeholder="__('All categories')" class="mt-1" />
            </div>
            <div class="space-y-1 text-sm text-gray-700">
                <label class="flex items-center gap-1.5"><input type="checkbox" name="unset" value="1" @checked($filters['unset'] ?? false) class="form-checkbox"> {{ __('No level set') }}</label>
                <label class="flex items-center gap-1.5"><input type="checkbox" name="kanban" value="1" @checked($filters['kanban'] ?? false) class="form-checkbox"> {{ __('Kanban only') }}</label>
            </div>
            <button class="btn-primary btn-sm">{{ __('Filter') }}</button>
        </form>

        @if ($errors->any())
            <div class="rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                {{ __('Nothing was saved. Fix the highlighted rows.') }}
                <ul class="mt-1 list-disc ps-5">
                    @foreach (collect($errors->messages())->unique()->take(5) as $messages)
                        <li>{{ $messages[0] }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('stock.levels.update') }}" class="card">
            @csrf @method('PUT')
            <input type="hidden" name="site_id" value="{{ $site->id }}">

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Item') }}</th>
                            <th class="num">{{ __('In stock') }}</th>
                            <th class="num">{{ __('Min level') }}</th>
                            <th>{{ __('Bin') }}</th>
                            <th>{{ __('Kanban') }}</th>
                            <th class="num">{{ __('Qty per bin') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            @php
                                $stock = $item->stocks->first();
                                $key = "rows.{$item->id}";
                                $kanban = (bool) old("{$key}.is_kanban", $stock?->is_kanban);
                            @endphp
                            <tr x-data="{ kanban: @js($kanban) }" @class(['bg-red-50' => $errors->has("{$key}.*")])>
                                <td>
                                    <span class="font-mono">{{ $item->sku }}</span>
                                    <span class="text-gray-700">{{ $item->name }}</span>
                                    <span class="block text-xs text-gray-500">{{ $item->category->name }} @if ($item->criticality) · {{ __('class :c', ['c' => $item->criticality->value]) }} @endif</span>
                                </td>
                                <td class="num">{{ \App\Support\Format::qty($stock?->qty ?? 0) }} {{ $item->uom }}</td>
                                <td class="num">
                                    <input name="rows[{{ $item->id }}][min_level]" inputmode="decimal" aria-label="{{ __('Min level for :sku', ['sku' => $item->sku]) }}"
                                           value="{{ old("{$key}.min_level", $stock && bccomp($stock->min_level, '0', 3) > 0 ? \App\Support\Format::qty($stock->min_level) : '') }}"
                                           :readonly="kanban" :class="kanban ? 'bg-gray-100 text-gray-400' : ''"
                                           class="form-input w-24 text-right @error("{$key}.min_level") border-red-500 @enderror">
                                </td>
                                <td>
                                    <input name="rows[{{ $item->id }}][bin]" maxlength="40" aria-label="{{ __('Bin for :sku', ['sku' => $item->sku]) }}"
                                           value="{{ old("{$key}.bin", $stock?->bin) }}" class="form-input w-32">
                                </td>
                                <td>
                                    <input type="hidden" name="rows[{{ $item->id }}][is_kanban]" value="0">
                                    <input type="checkbox" name="rows[{{ $item->id }}][is_kanban]" value="1" x-model="kanban"
                                           aria-label="{{ __('Kanban for :sku', ['sku' => $item->sku]) }}" class="form-checkbox">
                                </td>
                                <td class="num">
                                    <input name="rows[{{ $item->id }}][bin_qty]" inputmode="decimal" aria-label="{{ __('Quantity per bin for :sku', ['sku' => $item->sku]) }}"
                                           value="{{ old("{$key}.bin_qty", $stock?->bin_qty !== null ? \App\Support\Format::qty($stock->bin_qty) : '') }}"
                                           :readonly="!kanban" :class="!kanban ? 'bg-gray-100 text-gray-400' : ''"
                                           class="form-input w-24 text-right @error("{$key}.bin_qty") border-red-500 @enderror">
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-gray-500">{{ __('No items match.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($items->isNotEmpty())
                <div class="card-body border-t border-gray-100 flex items-center justify-between gap-3">
                    <p class="text-xs text-gray-500">{{ __('Saves the :n rows on this page.', ['n' => $items->count()]) }}</p>
                    <button class="btn-primary">{{ __('Save levels') }}</button>
                </div>
            @endif
        </form>

        {{ $items->links() }}
    </x-page>
</x-app-layout>
