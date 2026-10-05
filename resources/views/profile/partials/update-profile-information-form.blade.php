<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Your email address, role and site are managed by an administrator.') }}
        </p>
    </header>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <dl class="grid grid-cols-3 gap-2 text-sm">
            <dt class="text-gray-500">{{ __('Email') }}</dt>
            <dd class="col-span-2 text-gray-900">{{ $user->email }}</dd>
            <dt class="text-gray-500">{{ __('Role') }}</dt>
            <dd class="col-span-2 text-gray-900">{{ $user->role->label() }}</dd>
            <dt class="text-gray-500">{{ __('Site') }}</dt>
            <dd class="col-span-2 text-gray-900">{{ $user->site?->code ?? __('All sites') }}</dd>
        </dl>

        <label for="notify_low_stock" class="inline-flex items-center">
            <input id="notify_low_stock" type="checkbox" name="notify_low_stock" value="1" @checked(old('notify_low_stock', $user->notify_low_stock)) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
            <span class="ms-2 text-sm text-gray-600">{{ __('Send me the daily low-stock digest') }}</span>
        </label>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
