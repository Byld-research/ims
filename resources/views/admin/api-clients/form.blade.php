<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$client->exists ? __('Edit API client') : __('New API client')" />
    </x-slot>

    <x-page>
        <form method="POST" action="{{ $client->exists ? route('admin.api-clients.update', $client) : route('admin.api-clients.store') }}" class="card card-body max-w-xl space-y-5">
            @csrf
            @if ($client->exists) @method('PUT') @endif

            <x-field name="name" :label="__('Name')" required :hint="__('The application or team using it, e.g. Power BI reports.')">
                <x-input name="name" :value="$client->name" required maxlength="100" autofocus />
            </x-field>
            <x-field name="site_id" :label="__('Data from')" :hint="__('Limit every answer to one site, or leave All sites.')">
                <x-select name="site_id" :options="$sites" :value="$client->site_id" :placeholder="__('All sites')" />
            </x-field>
            <x-field name="notes" :label="__('Notes')" :hint="__('Who to contact, what it is used for.')">
                <x-textarea name="notes" rows="3" :value="$client->notes" />
            </x-field>
            @if ($client->exists)
                <x-checkbox name="is_active" :label="__('Active (the token works)')" :checked="$client->is_active" />
            @endif

            <div class="flex gap-2">
                <button class="btn-primary">{{ $client->exists ? __('Save') : __('Create and show token') }}</button>
                <a href="{{ $client->exists ? route('admin.api-clients.show', $client) : route('admin.api-clients.index') }}" class="btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </x-page>
</x-app-layout>
