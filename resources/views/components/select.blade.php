@props(['name', 'options' => [], 'value' => null, 'placeholder' => null])

{{-- $options: [value => label] or [group label => [value => label]] for optgroups. --}}
@php($selected = (string) old($name, $value instanceof \BackedEnum ? $value->value : $value))

<select name="{{ $name }}" id="{{ $attributes->get('id', $name) }}" {{ $attributes->except('id')->merge(['class' => 'form-input']) }}>
    @if ($placeholder !== null)
        <option value="">{{ $placeholder }}</option>
    @endif
    @foreach ($options as $key => $label)
        @if (is_array($label))
            <optgroup label="{{ $key }}">
                @foreach ($label as $optionValue => $optionLabel)
                    <option value="{{ $optionValue }}" @selected($selected === (string) $optionValue)>{{ $optionLabel }}</option>
                @endforeach
            </optgroup>
        @else
            <option value="{{ $key }}" @selected($selected === (string) $key)>{{ $label }}</option>
        @endif
    @endforeach
</select>
