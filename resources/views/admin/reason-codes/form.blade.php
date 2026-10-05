<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$code->exists ? __('Edit reason code') : __('New reason code')" />
    </x-slot>

    <x-page>
        <form method="POST" action="{{ $code->exists ? route('admin.reason-codes.update', $code) : route('admin.reason-codes.store') }}" class="card card-body max-w-xl space-y-5">
            @csrf
            @if ($code->exists) @method('PUT') @endif

            @if ($isSystem)
                <p class="rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-600">{{ __('The application posts this code itself. Only the label can change.') }}</p>
            @endif

            <x-field name="applies_to" :label="__('Used for')" required>
                @if ($isSystem)
                    <input type="hidden" name="applies_to" value="{{ $code->applies_to->value }}">
                @endif
                <x-select name="applies_to" :options="$scopes" :value="$code->applies_to" required :disabled="$isSystem" />
            </x-field>
            <x-field name="code" :label="__('Code')" required :hint="__('Capital letters, digits and underscores.')">
                <x-input name="code" :value="$code->code" required maxlength="20" class="font-mono uppercase" :readonly="$isSystem" />
            </x-field>
            <x-field name="label" :label="__('Label')" required>
                <x-input name="label" :value="$code->label" required maxlength="100" />
            </x-field>
            @if ($code->exists && ! $isSystem)
                <x-checkbox name="is_active" :label="__('Active (offered on forms)')" :checked="$code->is_active" />
            @endif

            <div class="flex gap-2">
                <button class="btn-primary">{{ __('Save') }}</button>
                <a href="{{ route('admin.reason-codes.index') }}" class="btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </x-page>
</x-app-layout>
