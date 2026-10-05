<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$machineType->exists ? __('Edit machine type') : __('New machine type')" />
    </x-slot>

    <x-page>
        <form method="POST" action="{{ $machineType->exists ? route('machine-types.update', $machineType) : route('machine-types.store') }}" class="card card-body max-w-2xl space-y-5">
            @csrf
            @if ($machineType->exists) @method('PUT') @endif

            <div class="grid gap-5 sm:grid-cols-3">
                <x-field name="code" :label="__('Code')" required :hint="__('e.g. TRUSS_SAW')">
                    <x-input name="code" :value="$machineType->code" required maxlength="30" class="font-mono uppercase" autofocus />
                </x-field>
                <x-field name="name" :label="__('Name')" required class="sm:col-span-2">
                    <x-input name="name" :value="$machineType->name" required maxlength="150" />
                </x-field>
            </div>

            <x-field name="description" :label="__('Description')">
                <x-textarea name="description" :value="$machineType->description" />
            </x-field>

            @if ($machineType->exists)
                <x-field name="is_active" :label="__('Status')">
                    <x-checkbox name="is_active" :label="__('Active')" :checked="$machineType->is_active" />
                </x-field>
            @endif

            <div class="flex gap-2">
                <button class="btn-primary">{{ __('Save') }}</button>
                <a href="{{ $machineType->exists ? route('machine-types.show', $machineType) : route('machine-types.index') }}" class="btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </x-page>
</x-app-layout>
