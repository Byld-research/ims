<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Adjust stock')" :subtitle="__('Corrections and opening balances. Mistakes are fixed with another adjustment, never by editing history.')" />
    </x-slot>

    <x-page>
        <form method="POST" action="{{ route('adjustments.store') }}" class="card card-body max-w-3xl space-y-5"
              x-data="adjustmentForm(@js(route('stock.lookup')), @js((string) old('direction', $direction)), @js(old('site_id', $siteId)), @js((string) old('qty', '')))"
              @item-selected.window="itemId = $event.detail.id; lookup()">
            @csrf

            <div class="grid gap-5 sm:grid-cols-3">
                <x-field name="site_id" :label="__('Site')" required>
                    @if ($sites->count() === 1)
                        <input type="hidden" name="site_id" value="{{ $sites->first()->id }}">
                        <input class="form-input bg-gray-100" value="{{ $sites->first()->code }} · {{ $sites->first()->name }}" disabled>
                    @else
                        <select name="site_id" id="site_id" class="form-input" x-model="siteId" @change="lookup()" required>
                            <option value="">{{ __('— Choose —') }}</option>
                            @foreach ($sites as $site)
                                <option value="{{ $site->id }}">{{ $site->code }} · {{ $site->name }}</option>
                            @endforeach
                        </select>
                    @endif
                </x-field>

                <x-field name="item_id" :label="__('Item')" required class="sm:col-span-2">
                    <x-item-picker name="item_id" :selected="old('item_id') ? App\Models\Item::find(old('item_id')) : $item" required />
                </x-field>
            </div>

            <div class="grid gap-5 sm:grid-cols-3">
                <x-field name="direction" :label="__('Direction')" required>
                    <div class="flex rounded-md shadow-sm" role="group">
                        <label class="flex-1 cursor-pointer rounded-s-md border px-3 py-2 text-center text-sm"
                               :class="direction === 'in' ? 'bg-green-50 border-green-400 text-green-800 font-semibold' : 'border-gray-300 text-gray-600'">
                            <input type="radio" name="direction" value="in" x-model="direction" class="sr-only"> {{ __('Increase') }}
                        </label>
                        <label class="flex-1 cursor-pointer rounded-e-md border border-s-0 px-3 py-2 text-center text-sm"
                               :class="direction === 'out' ? 'bg-red-50 border-red-400 text-red-800 font-semibold' : 'border-gray-300 text-gray-600'">
                            <input type="radio" name="direction" value="out" x-model="direction" class="sr-only"> {{ __('Decrease') }}
                        </label>
                    </div>
                </x-field>

                <x-field name="qty" :label="__('Quantity')" required>
                    <div class="flex items-center gap-2">
                        <x-input name="qty" x-model="qty" inputmode="decimal" required class="text-right" autocomplete="off" />
                        <span class="text-sm text-gray-500" x-text="stock ? stock.item.uom : ''"></span>
                    </div>
                </x-field>

                <x-field name="reason_code_id" :label="__('Reason')" required>
                    <x-select name="reason_code_id" :options="$reasons->pluck('label', 'id')->all()" :value="$reasonId" :placeholder="__('— Choose —')" required />
                </x-field>
            </div>

            <div x-show="direction === 'in'" class="grid gap-5 sm:grid-cols-3">
                <x-field name="unit_cost" :label="__('Unit cost (USD)')" class="sm:col-span-1">
                    <x-input name="unit_cost" inputmode="decimal" class="text-right" x-bind:required="direction === 'in' && stock && !stock.has_cost" />
                </x-field>
                <p class="sm:col-span-2 sm:pt-7 text-xs text-gray-500">
                    <span x-show="stock && !stock.has_cost">{{ __('Required: this item has no cost at this site yet. For an opening balance, use the last known price and say where it came from in the note.') }}</span>
                    <span x-show="stock && stock.has_cost">{{ __('Leave empty to add at the current average cost. A cost entered here moves the average.') }}</span>
                </p>
            </div>

            <x-field name="note" :label="__('Note')">
                <x-input name="note" maxlength="500" />
            </x-field>

            <div x-show="stock" x-cloak class="rounded-md bg-gray-50 px-4 py-3 text-sm">
                <span class="text-gray-600">{{ __('At') }} <span x-text="stock?.site"></span>:</span>
                <span class="font-semibold tabular-nums" x-text="format(stock?.qty)"></span>
                <span class="text-gray-500">→</span>
                <span class="font-semibold tabular-nums" :class="after() < 0 ? 'text-red-600' : 'text-gray-900'" x-text="format(after())"></span>
                <span x-text="stock?.item.uom"></span>
                <span x-show="after() < 0" class="ms-2 text-red-600">{{ __('Not enough stock.') }}</span>
            </div>

            <div class="flex gap-2">
                <button class="btn-primary">{{ __('Record adjustment') }}</button>
                <a href="{{ route('stock.index') }}" class="btn-secondary">{{ __('Done') }}</a>
            </div>
        </form>
    </x-page>
</x-app-layout>
