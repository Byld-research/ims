@props(['qty', 'threshold', 'severity' => 'good', 'uom' => '', 'kanban' => false])

{{--
    Stock against its minimum (or one kanban bin). The scale runs to twice the threshold, so the
    marker sits in the middle and "how far below" reads at a glance. Fill carries the severity;
    the track is a neutral step off the surface.
--}}
@php
    $q = max(0, (float) $qty);
    $t = max(0.0001, (float) $threshold);
    $scale = max($t * 2, $q);
    $fill = $q > 0 ? max(2, min(100, $q / $scale * 100)) : 0;
    $mark = $t / $scale * 100;
    $label = $kanban
        ? __(':qty :uom in stock, one bin is :t', ['qty' => \App\Support\Format::qty($qty), 'uom' => $uom, 't' => \App\Support\Format::qty($threshold)])
        : __(':qty :uom in stock, minimum :t', ['qty' => \App\Support\Format::qty($qty), 'uom' => $uom, 't' => \App\Support\Format::qty($threshold)]);
@endphp

<div {{ $attributes->merge(['class' => 'w-full']) }} role="meter" aria-valuemin="0" aria-valuemax="{{ $scale }}" aria-valuenow="{{ $q }}" aria-label="{{ $label }}" title="{{ $label }}">
    <div class="relative h-2.5 rounded-full" style="background: var(--meter-track)">
        <div class="absolute inset-y-0 left-0 rounded-full" style="width: {{ $fill }}%; background: var(--status-{{ $severity }})"></div>
        <div class="absolute -top-1 -bottom-1 w-0.5 rounded bg-gray-800" style="left: calc({{ $mark }}% - 1px)"></div>
    </div>
</div>
