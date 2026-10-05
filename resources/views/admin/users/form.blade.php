<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$user->exists ? __('Edit :name', ['name' => $user->name]) : __('New user')">
            @if ($user->exists)
                <form method="POST" action="{{ route('admin.users.reset-link', $user) }}">
                    @csrf
                    <button class="btn-secondary btn-sm">{{ __('Email a password reset link') }}</button>
                </form>
            @endif
        </x-page-header>
    </x-slot>

    <x-page>
        <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}"
              class="card card-body max-w-2xl space-y-5" x-data="{ role: @js(old('role', $user->role?->value)) }">
            @csrf
            @if ($user->exists) @method('PUT') @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="name" :label="__('Name')" required>
                    <x-input name="name" :value="$user->name" required maxlength="100" autofocus />
                </x-field>
                <x-field name="email" :label="__('Email (login)')" required>
                    <x-input type="email" name="email" :value="$user->email" required maxlength="150" />
                </x-field>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="role" :label="__('Role')" required
                         :hint="__('Operators read only. Managers record movements at their own site. Administrators work at every site.')">
                    <x-select name="role" :options="$roles" :value="$user->role?->value" x-model="role" required />
                </x-field>
                <x-field name="site_id" :label="__('Site')" x-show="role !== 'ADMIN'">
                    <x-select name="site_id" :options="$sites" :value="$user->site_id" :placeholder="__('— Choose —')" />
                </x-field>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="password" :label="$user->exists ? __('New password') : __('Password')" :required="! $user->exists"
                         :hint="$user->exists ? __('Leave empty to keep the current password.') : __('Give it to the person, or send a reset link afterwards.')">
                    <x-input type="password" name="password" autocomplete="new-password" :required="! $user->exists" />
                </x-field>
                <x-field name="password_confirmation" :label="__('Repeat password')">
                    <x-input type="password" name="password_confirmation" autocomplete="new-password" />
                </x-field>
            </div>

            <div class="flex flex-wrap gap-6">
                <x-checkbox name="notify_low_stock" :label="__('Daily low-stock digest by email')" :checked="$user->notify_low_stock" />
                @if ($user->exists)
                    <x-checkbox name="is_active" :label="__('Active (can log in)')" :checked="$user->is_active" />
                @endif
            </div>
            <x-input-error :messages="$errors->get('is_active')" />

            <div class="flex gap-2">
                <button class="btn-primary">{{ __('Save') }}</button>
                <a href="{{ route('admin.users.index') }}" class="btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </x-page>
</x-app-layout>
