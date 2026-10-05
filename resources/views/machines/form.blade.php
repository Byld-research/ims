@php
    // Per type: the proposed SKU and default name, so changing the type updates both (SPEC 5.8).
    $proposals = $types->mapWithKeys(fn ($t) => [$t->id => ['sku' => $t->nextSku(), 'name' => $t->name]]);
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$machine->exists ? __('Edit machine :sku', ['sku' => $machine->sku]) : __('Register machine')" />
    </x-slot>

    <x-page>
        <form method="POST" action="{{ $machine->exists ? route('machines.update', $machine) : route('machines.store') }}"
              class="card card-body max-w-2xl space-y-5"
              x-data="{
                  proposals: @js($proposals),
                  sku: @js(old('sku', $machine->sku)),
                  name: @js(old('name', $machine->name)),
                  typeChanged(id) {
                      const previous = Object.values(this.proposals);
                      const next = this.proposals[id];
                      if (!next) return;
                      if (!this.sku || previous.some(p => p.sku === this.sku)) this.sku = next.sku;
                      if (!this.name || previous.some(p => p.name === this.name)) this.name = next.name;
                  },
              }">
            @csrf
            @if ($machine->exists) @method('PUT') @endif

            @if ($locked)
                <p class="rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-600">
                    {{ __('Stock has been issued to this machine, so its SKU and type are fixed.') }}
                </p>
            @endif

            <div class="grid gap-5 sm:grid-cols-3">
                <x-field name="machine_type_id" :label="__('Type')" required class="sm:col-span-2">
                    @if ($locked)
                        <input type="hidden" name="machine_type_id" value="{{ $machine->machine_type_id }}">
                        <input class="form-input bg-gray-100" value="{{ $machine->machineType->label() }}" disabled>
                    @else
                        <x-select name="machine_type_id" :options="$types->mapWithKeys(fn ($t) => [$t->id => $t->label()])->all()"
                                  :value="$machine->machine_type_id" :placeholder="__('— Choose —')" required
                                  x-on:change="typeChanged($event.target.value)" />
                    @endif
                </x-field>

                <x-field name="sku" :label="__('Machine SKU')" required
                         :hint="$locked ? null : __('Serial + type letter. The next free serial is proposed.')">
                    <input type="text" name="sku" id="sku" x-model="sku" required maxlength="4"
                           @readonly($locked) class="form-input font-mono uppercase {{ $locked ? 'bg-gray-100' : '' }}">
                </x-field>
            </div>

            <div class="grid gap-5 sm:grid-cols-3">
                <x-field name="name" :label="__('Name')" required class="sm:col-span-2"
                         :hint="__('Proper name; may differ by revision or variant.')">
                    <input type="text" name="name" id="name" x-model="name" required maxlength="150" class="form-input">
                </x-field>

                <x-field name="revision" :label="__('Revision')" required :hint="__('e.g. 1.0, 2.0')">
                    <x-input name="revision" :value="$machine->revision" required maxlength="10" class="font-mono" />
                </x-field>
            </div>

            <x-field name="site_id" :label="__('Site')" required
                     :hint="$machine->exists ? __('Change to relocate the machine. Past movements stay at the site where they happened.') : null">
                <x-select name="site_id" :options="$sites" :value="$machine->site_id" :placeholder="__('— Choose —')" required />
            </x-field>

            @if ($machine->exists)
                <x-field name="is_active" :label="__('Status')" :hint="__('Inactive machines are not offered when issuing stock.')">
                    <x-checkbox name="is_active" :label="__('Active')" :checked="$machine->is_active" />
                </x-field>
            @endif

            <div class="flex gap-2">
                <button class="btn-primary">{{ __('Save') }}</button>
                <a href="{{ $machine->exists ? route('machines.show', $machine) : route('machines.index') }}" class="btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </x-page>
</x-app-layout>
