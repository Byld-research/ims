<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$supplier->exists ? __('Edit supplier') : __('New supplier')" />
    </x-slot>

    <x-page>
        <form method="POST" action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}" class="card card-body max-w-2xl space-y-5">
            @csrf
            @if ($supplier->exists) @method('PUT') @endif

            <x-field name="name" :label="__('Name')" required>
                <x-input name="name" :value="$supplier->name" required maxlength="150" autofocus />
            </x-field>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="contact_email" :label="__('Email')">
                    <x-input type="email" name="contact_email" :value="$supplier->contact_email" maxlength="150" />
                </x-field>
                <x-field name="contact_phone" :label="__('Phone')">
                    <x-input name="contact_phone" :value="$supplier->contact_phone" maxlength="50" />
                </x-field>
            </div>

            <x-field name="lead_time_days" :label="__('Typical lead time (days)')" :hint="__('Indicative only; never used in calculations.')" class="max-w-xs">
                <x-input type="number" name="lead_time_days" :value="$supplier->lead_time_days" min="0" max="1000" />
            </x-field>

            <x-field name="notes" :label="__('Notes')">
                <x-textarea name="notes" :value="$supplier->notes" rows="4" />
            </x-field>

            @if ($supplier->exists)
                <x-field name="is_active" :label="__('Status')">
                    <x-checkbox name="is_active" :label="__('Active')" :checked="$supplier->is_active" />
                </x-field>
            @endif

            <div class="flex gap-2">
                <button class="btn-primary">{{ __('Save') }}</button>
                <a href="{{ $supplier->exists ? route('suppliers.show', $supplier) : route('suppliers.index') }}" class="btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </x-page>
</x-app-layout>
