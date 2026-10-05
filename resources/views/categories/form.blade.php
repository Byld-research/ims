<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$category->exists ? __('Edit category') : __('New category')" />
    </x-slot>

    <x-page>
        <form method="POST" action="{{ $category->exists ? route('categories.update', $category) : route('categories.store') }}" class="card card-body max-w-2xl space-y-5">
            @csrf
            @if ($category->exists) @method('PUT') @endif

            <x-field name="name" :label="__('Name')" required>
                <x-input name="name" :value="$category->name" required maxlength="100" autofocus />
            </x-field>

            <x-field name="parent_id" :label="__('Parent')" :hint="__('Leave empty for a top-level group. Only one level of nesting is used.')">
                <x-select name="parent_id" :options="$parents" :value="$category->parent_id" :placeholder="__('— None (top level) —')" />
            </x-field>

            <x-field name="is_structural" :label="__('Structural')" :hint="__('Structural categories group others; items cannot be assigned to them.')">
                <x-checkbox name="is_structural" :label="__('Grouping only, no items')" :checked="$category->is_structural" />
            </x-field>

            <x-field name="default_bin" :label="__('Default bin')" :hint="__('Prefills the shelf reference for new items in this category.')">
                <x-input name="default_bin" :value="$category->default_bin" maxlength="40" />
            </x-field>

            <div class="flex gap-2">
                <button class="btn-primary">{{ __('Save') }}</button>
                <a href="{{ route('categories.index') }}" class="btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </x-page>
</x-app-layout>
