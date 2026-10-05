<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Users')" :subtitle="__('Accounts are created here; there is no self-registration.')">
            <x-export-link />
            <a href="{{ route('admin.users.create') }}" class="btn-primary">{{ __('New user') }}</a>
        </x-page-header>
    </x-slot>

    <x-page>
        <div class="card table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Email') }}</th>
                        <th>{{ __('Role') }}</th>
                        <th>{{ __('Site') }}</th>
                        <th>{{ __('Digest') }}</th>
                        <th>{{ __('Last login') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr @class(['text-gray-400' => ! $user->is_active])>
                            <td><a class="link" href="{{ route('admin.users.edit', $user) }}">{{ $user->name }}</a></td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->role->label() }}</td>
                            <td>{{ $user->site?->code ?? __('All sites') }}</td>
                            <td>{{ $user->notify_low_stock ? __('Yes') : '—' }}</td>
                            <td class="whitespace-nowrap">{{ $user->last_login_at ? \App\Support\Format::datetime($user->last_login_at) : __('never') }}</td>
                            <td><x-active-badge :active="$user->is_active" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-page>
</x-app-layout>
