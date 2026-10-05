<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Transfer in')" :subtitle="__('Enter a transfer when the goods arrive. There is no in-transit stock: both sites change at once.')" />
    </x-slot>

    <x-page>
        <form method="POST" action="{{ route('transfers.store') }}" class="card card-body max-w-3xl space-y-5"
              x-data="transferForm({
                  lookupUrl: @js(route('stock.lookup')),
                  fromId: @js(old('from_site_id', $fromId)),
                  toId: @js(old('to_site_id', $toId)),
                  qty: @js((string) old('qty', '')),
              })"
              @item-selected.window="lookup()">
            @csrf

            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="from_site_id" :label="__('From')" required>
                    <select name="from_site_id" id="from_site_id" class="form-input" x-model="fromId" @change="lookup()" required>
                        <option value="">{{ __('— Choose —') }}</option>
                        @foreach ($sites as $site)
                            <option value="{{ $site->id }}" :disabled="toId === '{{ $site->id }}'">{{ $site->code }} · {{ $site->name }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field name="to_site_id" :label="__('To (receiving here)')" required>
                    @if ($receiving->count() === 1)
                        <input type="hidden" name="to_site_id" value="{{ $receiving->first()->id }}">
                        <input class="form-input bg-gray-100" value="{{ $receiving->first()->code }} · {{ $receiving->first()->name }}" disabled>
                    @else
                        <select name="to_site_id" id="to_site_id" class="form-input" x-model="toId" @change="lookup()" required>
                            <option value="">{{ __('— Choose —') }}</option>
                            @foreach ($receiving as $site)
                                <option value="{{ $site->id }}">{{ $site->code }} · {{ $site->name }}</option>
                            @endforeach
                        </select>
                    @endif
                </x-field>
            </div>

            <x-field name="item_id" :label="__('Item')" required>
                <x-item-picker name="item_id" :selected="old('item_id') ? App\Models\Item::find(old('item_id')) : $item" required />
            </x-field>

            <div class="grid gap-5 sm:grid-cols-3">
                <x-field name="qty" :label="__('Quantity arrived')" required>
                    <div class="flex items-center gap-2">
                        <x-input name="qty" x-model="qty" inputmode="decimal" required class="text-right" autocomplete="off" />
                        <span class="text-sm text-gray-500" x-text="from ? from.item.uom : ''"></span>
                    </div>
                </x-field>
                <x-field name="note" :label="__('Note')" class="sm:col-span-2">
                    <x-input name="note" maxlength="500" :placeholder="__('Optional, e.g. carrier or packing slip')" />
                </x-field>
            </div>

            <div x-show="from && to" x-cloak class="grid gap-2 rounded-md bg-gray-50 px-4 py-3 text-sm sm:grid-cols-2">
                <div>
                    <span class="text-gray-600" x-text="from?.site + ':'"></span>
                    <span class="font-semibold tabular-nums" x-text="format(from?.qty)"></span> →
                    <span class="font-semibold tabular-nums" :class="(parseFloat(from?.qty) || 0) - change() < 0 ? 'text-red-600' : ''" x-text="format((parseFloat(from?.qty) || 0) - change())"></span>
                    <span x-show="(parseFloat(from?.qty) || 0) - change() < 0" class="block text-xs text-red-600">
                        {{ __('The sending site has not recorded this much. It must correct its stock with an adjustment first.') }}
                    </span>
                </div>
                <div>
                    <span class="text-gray-600" x-text="to?.site + ':'"></span>
                    <span class="font-semibold tabular-nums" x-text="format(to?.qty)"></span> →
                    <span class="font-semibold tabular-nums" x-text="format((parseFloat(to?.qty) || 0) + change())"></span>
                </div>
            </div>

            <div class="flex gap-2">
                <button class="btn-primary">{{ __('Record transfer') }}</button>
                <a href="{{ route('stock.index') }}" class="btn-secondary">{{ __('Done') }}</a>
            </div>
        </form>
    </x-page>
</x-app-layout>
