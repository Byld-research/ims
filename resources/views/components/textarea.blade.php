@props(['name', 'value' => null, 'rows' => 3])

<textarea name="{{ $name }}" id="{{ $name }}" rows="{{ $rows }}"
          {{ $attributes->merge(['class' => 'form-input']) }}>{{ old($name, $value) }}</textarea>
