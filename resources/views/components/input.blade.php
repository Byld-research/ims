@props(['name', 'value' => null, 'type' => 'text'])

<input type="{{ $type }}" name="{{ $name }}" id="{{ $attributes->get('id', $name) }}"
       value="{{ old($name, $value) }}"
       {{ $attributes->except('id')->merge(['class' => 'form-input']) }}>
