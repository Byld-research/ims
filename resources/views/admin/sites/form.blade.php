<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$site->exists ? __('Edit :code', ['code' => $site->code]) : __('New site')" />
    </x-slot>

    <x-page>
        <form method="POST" action="{{ $site->exists ? route('admin.sites.update', $site) : route('admin.sites.store') }}" class="card card-body max-w-2xl space-y-5">
            @csrf
            @if ($site->exists) @method('PUT') @endif

            <div class="grid gap-5 sm:grid-cols-3">
                <x-field name="code" :label="__('Code')" required :hint="$site->exists ? __('Fixed once created.') : __('e.g. BPC003')">
                    <x-input name="code" :value="$site->code" required maxlength="10" class="font-mono uppercase" :readonly="$site->exists" />
                </x-field>
                <x-field name="name" :label="__('Name')" required class="sm:col-span-2">
                    <x-input name="name" :value="$site->name" required maxlength="100" />
                </x-field>
            </div>

            <div class="grid gap-5 sm:grid-cols-3">
                <x-field name="state" :label="__('State')" required>
                    <x-input name="state" :value="$site->state" required maxlength="2" class="uppercase" />
                </x-field>
                <x-field name="timezone" :label="__('Time zone')" required class="sm:col-span-2" :hint="__('Dates and times on screen and the digest hour follow it.')">
                    <x-select name="timezone" :options="$timezones" :value="$site->timezone" required />
                </x-field>
            </div>

            <x-field name="digest_hour" :label="__('Daily digest at')" required :hint="__('Local time. The digest goes only to users who opted in, and only when something needs attention.')" class="max-w-xs">
                <x-select name="digest_hour" :options="$hours" :value="$site->digest_hour" required />
            </x-field>

            @if ($site->exists)
                <x-field name="is_active" :label="__('Status')">
                    <x-checkbox name="is_active" :label="__('Active')" :checked="$site->is_active" />
                </x-field>
            @endif

            <div class="flex gap-2">
                <button class="btn-primary">{{ __('Save') }}</button>
                <a href="{{ route('admin.sites.index') }}" class="btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </x-page>
</x-app-layout>
