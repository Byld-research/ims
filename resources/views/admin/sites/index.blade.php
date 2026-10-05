<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Sites')">
            <a href="{{ route('admin.sites.create') }}" class="btn-primary">{{ __('New site') }}</a>
        </x-page-header>
    </x-slot>

    <x-page>
        <div class="card table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Code') }}</th>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('State') }}</th>
                        <th>{{ __('Time zone') }}</th>
                        <th>{{ __('Daily digest') }}</th>
                        <th class="num">{{ __('Machines') }}</th>
                        <th class="num">{{ __('Users') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sites as $site)
                        <tr>
                            <td class="font-mono"><a class="link" href="{{ route('admin.sites.edit', $site) }}">{{ $site->code }}</a></td>
                            <td>{{ $site->name }}</td>
                            <td>{{ $site->state }}</td>
                            <td>{{ $site->timezone }}</td>
                            <td>{{ sprintf('%02d:00', $site->digest_hour) }} {{ __('local') }}</td>
                            <td class="num">{{ $site->machines_count }}</td>
                            <td class="num">{{ $site->users_count }}</td>
                            <td><x-active-badge :active="$site->is_active" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-page>
</x-app-layout>
