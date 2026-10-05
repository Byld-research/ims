@props(['severity', 'label'])

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-semibold text-gray-900']) }}
      style="background: var(--status-{{ $severity }}-tint)">
    <x-status-icon :severity="$severity" size="h-3.5 w-3.5" />
    {{ $label }}
</span>
