@props(['name', 'label', 'checked' => false])

<label class="inline-flex items-center gap-2 text-sm text-gray-700">
    <input type="hidden" name="{{ $name }}" value="0">
    <input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $checked)) {{ $attributes->merge(['class' => 'form-checkbox']) }}>
    {{ $label }}
</label>
