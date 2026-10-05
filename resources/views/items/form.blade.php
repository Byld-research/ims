<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$item->exists ? __('Edit :sku', ['sku' => $item->sku]) : __('New item')" />
    </x-slot>

    <x-page>
        <form method="POST" action="{{ $item->exists ? route('items.update', $item) : route('items.store') }}" class="card card-body max-w-3xl space-y-5">
            @csrf
            @if ($item->exists) @method('PUT') @endif

            <div class="grid gap-5 sm:grid-cols-3">
                <x-field name="sku" :label="__('SKU')" required class="sm:col-span-1"
                         :hint="$skuLocked ? __('Locked: the item has stock movements.') : config('ims.sku_pattern_hint')">
                    <x-input name="sku" :value="$item->sku" required maxlength="40" :readonly="$skuLocked"
                             class="font-mono {{ $skuLocked ? 'bg-gray-100' : '' }}" autofocus />
                </x-field>

                <x-field name="name" :label="__('Name')" required class="sm:col-span-2">
                    <x-input name="name" :value="$item->name" required maxlength="200" />
                </x-field>
            </div>

            <div class="grid gap-5 sm:grid-cols-3">
                <x-field name="category_id" :label="__('Category')" required class="sm:col-span-2">
                    <x-select name="category_id" :options="$categories" :value="$item->category_id" :placeholder="__('— Choose —')" required />
                </x-field>

                <x-field name="uom" :label="__('Unit of measure')" required>
                    <x-input name="uom" :value="$item->uom" list="uom-options" required maxlength="20" />
                    <datalist id="uom-options">
                        @foreach (config('ims.uoms') as $uom)
                            <option value="{{ $uom }}">
                        @endforeach
                    </datalist>
                </x-field>
            </div>

            <x-field name="description" :label="__('Description')">
                <x-textarea name="description" :value="$item->description" />
            </x-field>

            <div class="grid gap-5 sm:grid-cols-3">
                <x-field name="manufacturer" :label="__('Manufacturer')">
                    <x-input name="manufacturer" :value="$item->manufacturer" maxlength="100" />
                </x-field>
                <x-field name="mpn" :label="__('Manufacturer part no.')">
                    <x-input name="mpn" :value="$item->mpn" maxlength="80" />
                </x-field>
                <x-field name="drawing_no" :label="__('Drawing no.')">
                    <x-input name="drawing_no" :value="$item->drawing_no" maxlength="80" />
                </x-field>
            </div>

            <div class="grid gap-5 sm:grid-cols-3">
                <x-field name="criticality" :label="__('Criticality')" :hint="__('A: failure stops production and the part is hard to get.')">
                    <x-select name="criticality" :options="['A' => 'A', 'B' => 'B', 'C' => 'C']" :value="$item->criticality" :placeholder="__('— Not set —')" />
                </x-field>

                @if ($item->exists)
                    <x-field name="is_active" :label="__('Status')" :hint="__('Inactive items are hidden from pickers and lists but keep their history.')" class="sm:col-span-2">
                        <x-checkbox name="is_active" :label="__('Active')" :checked="$item->is_active" />
                    </x-field>
                @endif
            </div>

            <div class="flex gap-2">
                <button class="btn-primary">{{ __('Save') }}</button>
                <a href="{{ $item->exists ? route('items.show', $item) : route('stock.index') }}" class="btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </x-page>
</x-app-layout>
