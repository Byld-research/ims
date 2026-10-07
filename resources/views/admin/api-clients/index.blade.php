<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('API clients')" :subtitle="__('Applications that read data through the API. Read-only; each has its own token.')">
            <x-export-link />
            <a href="{{ route('admin.api-clients.create') }}" class="btn-primary">{{ __('New API client') }}</a>
        </x-page-header>
    </x-slot>

    <x-page>
        <div class="card table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Data from') }}</th>
                        <th>{{ __('Token') }}</th>
                        <th>{{ __('Last used') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clients as $client)
                        <tr>
                            <td><a class="link" href="{{ route('admin.api-clients.show', $client) }}">{{ $client->name }}</a></td>
                            <td>{{ $client->site?->code ?? __('All sites') }}</td>
                            <td>{{ $client->tokens->isNotEmpty() ? __('Issued') : __('None') }}</td>
                            <td>{{ $client->tokens->max('last_used_at') ? App\Support\Format::datetime($client->tokens->max('last_used_at')) : __('never') }}</td>
                            <td><x-active-badge :active="$client->is_active" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-gray-500">{{ __('No API clients yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-500">{{ __('Developers: the API description is at') }} <span class="font-mono">{{ url('/api/v1/openapi.yaml') }}</span></p>
    </x-page>
</x-app-layout>
