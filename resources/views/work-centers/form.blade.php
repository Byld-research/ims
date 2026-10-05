<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$workCenter->exists ? __('Edit work centre') : __('New work centre')" />
    </x-slot>

    <x-page>
        <form method="POST" action="{{ $workCenter->exists ? route('work-centers.update', $workCenter) : route('work-centers.store') }}" class="card card-body max-w-2xl space-y-5">
            @csrf
            @if ($workCenter->exists) @method('PUT') @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="site_id" :label="__('Site')" required :hint="$siteLocked ? __('Locked: stock has been issued to this work centre.') : null">
                    @if ($siteLocked)
                        <input type="hidden" name="site_id" value="{{ $workCenter->site_id }}">
                        <x-select name="site_id_display" :options="$sites" :value="$workCenter->site_id" disabled />
                    @else
                        <x-select name="site_id" :options="$sites" :value="$workCenter->site_id" :placeholder="__('— Choose —')" required />
                    @endif
                </x-field>
                <x-field name="machine_type_id" :label="__('Machine type')" :hint="__('Gives the work centre its parts list.')">
                    <x-select name="machine_type_id" :options="$machineTypes" :value="$workCenter->machine_type_id" :placeholder="__('— None —')" />
                </x-field>
            </div>

            <div class="grid gap-5 sm:grid-cols-3">
                <x-field name="code" :label="__('Code')" required :hint="__('e.g. 004C')">
                    <x-input name="code" :value="$workCenter->code" required maxlength="30" class="font-mono" />
                </x-field>
                <x-field name="name" :label="__('Name')" required class="sm:col-span-2">
                    <x-input name="name" :value="$workCenter->name" required maxlength="150" />
                </x-field>
            </div>

            @if ($workCenter->exists)
                <x-field name="is_active" :label="__('Status')">
                    <x-checkbox name="is_active" :label="__('Active')" :checked="$workCenter->is_active" />
                </x-field>
            @endif

            <div class="flex gap-2">
                <button class="btn-primary">{{ __('Save') }}</button>
                <a href="{{ $workCenter->exists ? route('work-centers.show', $workCenter) : route('work-centers.index') }}" class="btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </x-page>
</x-app-layout>
