<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$machineType->code.' · '.$machineType->name"
                       :subtitle="__('Last serial :serial · next machine: :next', ['serial' => sprintf('%03d', $machineType->last_serial), 'next' => $machineType->nextSku()])">
            <x-active-badge :active="$machineType->is_active" />
            <x-export-link />
            @can('update', $machineType)
                <a href="{{ route('machine-types.edit', $machineType) }}" class="btn-secondary">{{ __('Edit') }}</a>
            @endcan
        </x-page-header>
    </x-slot>

    <x-page>
        @if ($machineType->description)
            <section class="card card-body text-sm text-gray-700 whitespace-pre-line">{{ $machineType->description }}</section>
        @endif

        @if (session('importErrors'))
            <section class="card card-body border border-red-200">
                <h3 class="card-title text-red-700">{{ __('Import problems') }}</h3>
                <ul class="mt-2 list-disc ps-5 text-sm text-red-700 space-y-0.5">
                    @foreach (session('importErrors') as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="card">
            <div class="card-body pb-2 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="card-title">{{ __('Parts list') }} <span class="text-gray-400 font-normal">({{ $partsList->count() }})</span></h3>
                    <p class="text-sm text-gray-500">{{ __('Lines without a revision apply to every revision. Informational: nothing is reserved or restricted.') }}</p>
                </div>
                @can('update', $machineType)
                    <form method="POST" action="{{ route('machine-types.import', $machineType) }}" enctype="multipart/form-data"
                          class="flex flex-wrap items-center gap-2 text-sm">
                        @csrf
                        <input type="file" name="file" accept=".csv,text/csv" required class="text-sm file:me-2 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm">
                        <button class="btn-secondary btn-sm">{{ __('Import CSV') }}</button>
                        <span class="w-full text-xs text-gray-500">
                            {{ __('Columns: :columns. An empty revision means all revisions. Existing lines are updated by SKU and revision; nothing is removed.', ['columns' => implode(', ', App\Services\PartsListImporter::COLUMNS)]) }}
                        </span>
                        <x-input-error :messages="$errors->get('file')" />
                    </form>
                @endcan
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Item') }}</th>
                            <th>{{ __('Revision') }}</th>
                            <th>{{ __('Reference') }}</th>
                            <th class="num">{{ __('Qty / machine') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Note') }}</th>
                            @can('update', $machineType)<th></th>@endcan
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($partsList as $line)
                            <tr x-data="{ editing: false }">
                                <td>
                                    <a class="link font-mono" href="{{ route('items.show', $line->item) }}">{{ $line->item->sku }}</a>
                                    <span class="text-gray-700">{{ $line->item->name }}</span>
                                    @unless ($line->item->is_active)<span class="badge-gray ms-1">{{ __('Inactive') }}</span>@endunless
                                </td>
                                <td x-show="!editing">
                                    @if ($line->revision)
                                        <span class="badge-indigo">{{ $line->revision }}</span>
                                    @else
                                        <span class="text-gray-500">{{ __('All') }}</span>
                                    @endif
                                </td>
                                <td x-show="!editing">{{ $line->reference ?? '—' }}</td>
                                <td class="num" x-show="!editing">{{ $line->qty_per_machine !== null ? \App\Support\Format::qty($line->qty_per_machine).' '.$line->item->uom : '—' }}</td>
                                <td x-show="!editing">@if ($line->is_consumable)<span class="badge-amber">{{ __('Consumable') }}</span>@endif</td>
                                <td x-show="!editing" class="text-gray-600">{{ $line->note }}</td>
                                @can('update', $machineType)
                                    <td colspan="5" x-show="editing" x-cloak>
                                        <form method="POST" action="{{ route('machine-type-items.update', $line) }}" class="flex flex-wrap items-center gap-2">
                                            @csrf @method('PUT')
                                            <input name="revision" value="{{ $line->revision }}" placeholder="{{ __('All revisions') }}" list="revision-options" maxlength="10" class="form-input w-28 font-mono">
                                            <input name="reference" value="{{ $line->reference }}" placeholder="{{ __('Reference') }}" maxlength="80" class="form-input w-32">
                                            <input name="qty_per_machine" value="{{ $line->qty_per_machine }}" placeholder="{{ __('Qty') }}" inputmode="decimal" class="form-input w-20 text-right">
                                            <label class="inline-flex items-center gap-1.5 text-sm text-gray-700">
                                                <input type="hidden" name="is_consumable" value="0">
                                                <input type="checkbox" name="is_consumable" value="1" @checked($line->is_consumable) class="form-checkbox">
                                                {{ __('Consumable') }}
                                            </label>
                                            <input name="note" value="{{ $line->note }}" placeholder="{{ __('Note') }}" maxlength="255" class="form-input w-48">
                                            <button class="btn-primary btn-sm">{{ __('Save') }}</button>
                                            <button type="button" class="btn-secondary btn-sm" @click="editing = false">{{ __('Cancel') }}</button>
                                        </form>
                                    </td>
                                    <td class="text-right whitespace-nowrap" x-show="!editing">
                                        <button type="button" class="link text-sm" @click="editing = true">{{ __('Edit') }}</button>
                                        <form method="POST" action="{{ route('machine-type-items.destroy', $line) }}" class="inline"
                                              @submit="if (!window.confirm(@js(__('Remove :sku from the parts list?', ['sku' => $line->item->sku])))) $event.preventDefault()">
                                            @csrf @method('DELETE')
                                            <button class="btn-link-danger ms-2">{{ __('Remove') }}</button>
                                        </form>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-gray-500">{{ __('The parts list is empty.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @can('update', $machineType)
                <form method="POST" action="{{ route('machine-types.items.store', $machineType) }}" class="card-body border-t border-gray-100 grid gap-3 sm:grid-cols-12 items-start">
                    @csrf
                    <div class="sm:col-span-4">
                        <x-field name="item_id" :label="__('Item')" required>
                            <x-item-picker name="item_id" required />
                        </x-field>
                    </div>
                    <div class="sm:col-span-1">
                        <x-field name="revision" :label="__('Revision')">
                            <x-input name="revision" list="revision-options" maxlength="10" class="font-mono" :placeholder="__('All')" />
                        </x-field>
                    </div>
                    <div class="sm:col-span-1">
                        <x-field name="reference" :label="__('Reference')">
                            <x-input name="reference" maxlength="80" />
                        </x-field>
                    </div>
                    <div class="sm:col-span-1">
                        <x-field name="qty_per_machine" :label="__('Qty / machine')">
                            <x-input name="qty_per_machine" inputmode="decimal" class="text-right" />
                        </x-field>
                    </div>
                    <div class="sm:col-span-4 space-y-2">
                        <x-field name="note" :label="__('Note')">
                            <x-input name="note" maxlength="255" />
                        </x-field>
                        <x-checkbox name="is_consumable" :label="__('Consumable (wears out)')" />
                    </div>
                    <div class="sm:col-span-1 sm:pt-6">
                        <button class="btn-primary w-full">{{ __('Add') }}</button>
                    </div>
                </form>
            @endcan
        </section>

        <datalist id="revision-options">
            @foreach ($revisions as $revision)
                <option value="{{ $revision }}">
            @endforeach
        </datalist>

        <section class="card">
            <div class="card-body pb-2 flex items-center justify-between">
                <h3 class="card-title">{{ __('Registered machines') }}</h3>
                @can('create', App\Models\Machine::class)
                    <a href="{{ route('machines.create', ['type' => $machineType->id]) }}" class="btn-secondary btn-sm">{{ __('Register :sku', ['sku' => $machineType->nextSku()]) }}</a>
                @endcan
            </div>
            <div class="table-wrap">
                <table class="table">
                    <tbody>
                        @forelse ($machineType->machines as $machine)
                            <tr>
                                <td class="font-mono"><a class="link" href="{{ route('machines.show', $machine) }}">{{ $machine->sku }}</a></td>
                                <td>{{ $machine->displayName() }}</td>
                                <td>{{ $machine->site->code }}</td>
                                <td><x-active-badge :active="$machine->is_active" /></td>
                            </tr>
                        @empty
                            <tr><td class="text-gray-500">{{ __('No machine of this type is registered.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </x-page>
</x-app-layout>
