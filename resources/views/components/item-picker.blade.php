@props(['name' => 'item_id', 'selected' => null, 'placeholder' => 'Search by SKU or name…', 'required' => false])

{{-- Type-ahead item search backed by items.search. $selected: an Item or null. --}}
@php($initial = $selected ? ['id' => $selected->id, 'label' => $selected->sku.' · '.$selected->name] : null)

<div x-data="itemPicker(@js(route('items.search')), @js($initial))" class="relative" {{ $attributes }}>
    <input type="hidden" name="{{ $name }}" :value="selected ? selected.id : ''">
    <input type="text" id="{{ $name }}" autocomplete="off" class="form-input"
           placeholder="{{ $placeholder }}" @if ($required) required @endif
           x-model="query" @input.debounce.250ms="search()" @focus="open = results.length > 0"
           @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
           @keydown.enter="if (open && results.length) { $event.preventDefault(); choose(results[active]); }"
           @keydown.escape="open = false" @click.outside="open = false">
    <ul x-show="open" x-cloak
        class="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-md border border-gray-200 bg-white py-1 text-sm shadow-lg">
        <template x-for="(item, index) in results" :key="item.id">
            <li @mousedown.prevent="choose(item)" @mouseenter="active = index"
                :class="index === active ? 'bg-indigo-50' : ''" class="cursor-pointer px-3 py-1.5">
                <span class="font-mono text-gray-900" x-text="item.sku"></span>
                <span class="text-gray-700" x-text="item.name"></span>
                <span class="text-gray-400" x-text="item.uom"></span>
            </li>
        </template>
    </ul>
    <p x-show="query.length >= 2 && !loading && searched && results.length === 0" x-cloak class="mt-1 text-xs text-gray-500">
        {{ __('No active item matches.') }}
    </p>
</div>
