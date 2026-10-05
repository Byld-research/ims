@props(['severity', 'size' => 'h-5 w-5'])

{{-- One shape per state, so the state reads without colour (dataviz: status needs icon + label). --}}
<svg {{ $attributes->merge(['class' => $size.' shrink-0']) }} viewBox="0 0 20 20" aria-hidden="true" style="color: var(--status-{{ $severity }})">
    @switch($severity)
        @case('critical')
            <path fill="currentColor" d="M10 1.5a8.5 8.5 0 1 0 0 17 8.5 8.5 0 0 0 0-17Zm3.53 5.03L11.06 9l2.47 2.47-1.06 1.06L10 10.06l-2.47 2.47-1.06-1.06L8.94 9 6.47 6.53l1.06-1.06L10 7.94l2.47-2.47 1.06 1.06Z"/>
            @break
        @case('serious')
            <path fill="currentColor" d="M8.7 2.6a1.5 1.5 0 0 1 2.6 0l7 12.2A1.5 1.5 0 0 1 17 17H3a1.5 1.5 0 0 1-1.3-2.2l7-12.2ZM9.25 7v4.5h1.5V7h-1.5Zm.75 7.75a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z"/>
            @break
        @case('warning')
            <path fill="currentColor" d="M10 1.5a8.5 8.5 0 1 0 0 17 8.5 8.5 0 0 0 0-17ZM9.25 5.5v6h1.5v-6h-1.5Zm.75 9.25a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z"/>
            @break
        @default
            <path fill="currentColor" d="M10 1.5a8.5 8.5 0 1 0 0 17 8.5 8.5 0 0 0 0-17Zm4.03 6.03-5 5a.75.75 0 0 1-1.06 0l-2.5-2.5 1.06-1.06L8.5 10.94l4.47-4.47 1.06 1.06Z"/>
    @endswitch
</svg>
