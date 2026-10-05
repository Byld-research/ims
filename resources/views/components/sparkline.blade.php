@props(['values' => [], 'label' => ''])

{{--
    Weekly usage, oldest to newest (dataviz stat-tile trend): de-emphasis gray line, the current
    week as an accent dot, no axes. The numbers are in the title and aria-label, so nothing is
    colour- or hover-only.
--}}
@php
    $values = array_values($values);
    $n = count($values);
    $max = max(1, ...($values ?: [0]));
    $w = 96; $h = 24; $pad = 3;
    $points = collect($values)->map(fn ($v, $i) => [
        round($pad + ($n > 1 ? $i / ($n - 1) : 0.5) * ($w - 2 * $pad), 1),
        round($h - $pad - ($v / $max) * ($h - 2 * $pad), 1),
    ]);
    $total = array_sum($values);
    $text = $label.': '.collect($values)->map(fn ($v) => \App\Support\Format::qty((string) $v))->join(', ');
@endphp

@if ($total > 0)
    <svg {{ $attributes->merge(['class' => 'overflow-visible']) }} width="{{ $w }}" height="{{ $h }}" viewBox="0 0 {{ $w }} {{ $h }}" role="img" aria-label="{{ $text }}">
        <title>{{ $text }}</title>
        <line x1="{{ $pad }}" x2="{{ $w - $pad }}" y1="{{ $h - $pad }}" y2="{{ $h - $pad }}" stroke="#e7e6e2" stroke-width="1" />
        <polyline fill="none" stroke="#8f8e88" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"
                  points="{{ $points->map(fn ($p) => implode(',', $p))->join(' ') }}" />
        @php($last = $points->last())
        <circle cx="{{ $last[0] }}" cy="{{ $last[1] }}" r="3" fill="#2a78d6" stroke="#fff" stroke-width="1.5" />
    </svg>
@else
    <span {{ $attributes->merge(['class' => 'text-xs text-gray-400']) }}>{{ __('not used') }}</span>
@endif
