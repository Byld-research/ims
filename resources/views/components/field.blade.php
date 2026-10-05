@props(['name', 'label', 'hint' => null, 'required' => false])

<div {{ $attributes }}>
    <label for="{{ $name }}" class="block text-sm font-medium text-gray-700">
        {{ $label }}@if ($required)<span class="text-red-600"> *</span>@endif
    </label>
    <div class="mt-1">{{ $slot }}</div>
    @if ($hint)
        <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
    @endif
    <x-input-error class="mt-1" :messages="$errors->get(str_replace(['[', ']'], ['.', ''], $name))" />
</div>
