<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Issue stock')" :subtitle="__('Item, destination, quantity.')">
            <a href="{{ route('transfers.create') }}" class="btn-secondary btn-sm">{{ __('Transfer in from another site') }}</a>
        </x-page-header>
    </x-slot>

    <x-page>
        <form method="POST" action="{{ route('issues.store') }}" class="card card-body max-w-3xl space-y-5"
              x-data="issueForm({
                  lookupUrl: @js(route('stock.lookup')),
                  siteId: @js(old('site_id', $siteId)),
                  machines: @js($machines),
                  machineId: @js(old('machine_id', $machineId)),
                  mode: @js(old('mode', $mode)),
                  qty: @js((string) old('qty', '')),
              })"
              @item-selected.window="lookup($event.detail.id)">
            @csrf

            @if ($sites->count() === 1)
                <input type="hidden" name="site_id" value="{{ $sites->first()->id }}">
            @else
                <x-field name="site_id" :label="__('Site')" required class="max-w-xs">
                    <select name="site_id" id="site_id" class="form-input" x-model="siteId" @change="siteChanged()" required>
                        <option value="">{{ __('— Choose —') }}</option>
                        @foreach ($sites as $site)
                            <option value="{{ $site->id }}">{{ $site->code }} · {{ $site->name }}</option>
                        @endforeach
                    </select>
                </x-field>
            @endif

            {{-- 1. Item --}}
            <x-field name="item_id" :label="__('1 · Item')" required>
                <x-item-picker name="item_id" :selected="old('item_id') ? App\Models\Item::find(old('item_id')) : $item" required />
            </x-field>

            {{-- 2. Destination --}}
            <div>
                <div class="flex items-center justify-between">
                    <span class="block text-sm font-medium text-gray-700">{{ __('2 · Destination') }}<span class="text-red-600"> *</span></span>
                    <div class="flex rounded-md text-xs" role="group">
                        <label class="cursor-pointer rounded-s-md border px-2.5 py-1" :class="mode === 'machine' ? 'bg-indigo-50 border-indigo-400 text-indigo-800 font-semibold' : 'border-gray-300 text-gray-600'">
                            <input type="radio" name="mode" value="machine" x-model="mode" class="sr-only"> {{ __('Machine') }}
                        </label>
                        <label class="cursor-pointer rounded-e-md border border-s-0 px-2.5 py-1" :class="mode === 'general' ? 'bg-indigo-50 border-indigo-400 text-indigo-800 font-semibold' : 'border-gray-300 text-gray-600'">
                            <input type="radio" name="mode" value="general" x-model="mode" class="sr-only"> {{ __('General use') }}
                        </label>
                    </div>
                </div>
                <div class="mt-1" x-show="mode === 'machine'">
                    <select name="machine_id" id="machine_id" x-model="machineId" class="form-input" :required="mode === 'machine'" :disabled="mode !== 'machine'" aria-label="{{ __('Machine') }}">
                        <option value="">{{ __('— Choose the machine —') }}</option>
                        <template x-for="machine in siteMachines()" :key="machine.id">
                            <option :value="machine.id" x-text="machine.label" :selected="String(machine.id) === machineId"></option>
                        </template>
                    </select>
                    <p x-show="siteId && siteMachines().length === 0" class="mt-1 text-xs text-gray-500">{{ __('No active machine is registered at this site.') }}</p>
                    <x-input-error class="mt-1" :messages="$errors->get('machine_id')" />
                </div>
                <div class="mt-1" x-show="mode === 'general'" x-cloak>
                    <select name="reason_code_id" id="reason_code_id" class="form-input" :required="mode === 'general'" :disabled="mode !== 'general'" aria-label="{{ __('Used for') }}">
                        <option value="">{{ __('— What was it used for? —') }}</option>
                        @foreach ($reasons as $reason)
                            <option value="{{ $reason->id }}" @selected((int) old('reason_code_id', $reasonId) === $reason->id)>{{ $reason->label }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-1" :messages="$errors->get('reason_code_id')" />
                </div>
            </div>

            {{-- 3. Quantity --}}
            <div class="grid gap-5 sm:grid-cols-3">
                <x-field name="qty" :label="__('3 · Quantity')" required>
                    <div class="flex items-center gap-2">
                        <x-input name="qty" x-model="qty" inputmode="decimal" required class="text-right" autocomplete="off" />
                        <span class="text-sm text-gray-500" x-text="stock ? stock.item.uom : ''"></span>
                    </div>
                </x-field>
                <x-field name="note" :label="__('Note')" class="sm:col-span-2">
                    <x-input name="note" maxlength="500" :placeholder="__('Optional, e.g. work order or reason')" />
                </x-field>
            </div>

            <div x-show="stock" x-cloak class="rounded-md bg-gray-50 px-4 py-3 text-sm">
                <span class="text-gray-600">{{ __('At') }} <span x-text="stock?.site"></span>:</span>
                <span class="font-semibold tabular-nums" x-text="format(stock?.qty)"></span>
                <span class="text-gray-500">→</span>
                <span class="font-semibold tabular-nums" :class="after() < 0 ? 'text-red-600' : 'text-gray-900'" x-text="format(after())"></span>
                <span x-text="stock?.item.uom"></span>
                <span x-show="stock?.bin" class="ms-2 text-gray-500">· {{ __('location') }} <span x-text="stock?.bin"></span></span>
                <span x-show="after() < 0" class="ms-2 text-red-600">{{ __('Not enough stock.') }}</span>
            </div>

            <div class="flex gap-2">
                <button class="btn-primary">{{ __('Issue') }}</button>
                <a href="{{ route('stock.index') }}" class="btn-secondary">{{ __('Done') }}</a>
            </div>
        </form>
    </x-page>
</x-app-layout>
