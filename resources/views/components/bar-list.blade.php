@props(['rows', 'label', 'caption' => null])

{{--
    Horizontal bars for one series of magnitudes (dataviz: one hue, ≤24px bars, 4px rounded
    data end, square at the baseline, value at the tip in text ink, hover tooltip on the mark).
    $rows: list of ['label' => string, 'value' => numeric string, 'href' => ?string, 'detail' => ?string]
--}}
@php
    $max = collect($rows)->max(fn ($row) => (float) $row['value']) ?: 1;
@endphp

<figure {{ $attributes }}>
    <ul class="space-y-1.5" role="list" aria-label="{{ $label }}">
        @foreach ($rows as $row)
            {{-- Share of the largest value; the bar spans that share of the track minus room for the label. --}}
            @php($share = max(0.005, round((float) $row['value'] / $max, 4)))
            <li class="group grid grid-cols-[minmax(0,11rem)_1fr] items-center gap-3 text-sm"
                x-data="{ tip: false }" @mouseenter="tip = true" @mouseleave="tip = false"
                @focusin="tip = true" @focusout="tip = false">
                <span class="truncate text-gray-700" title="{{ $row['label'] }}">
                    @if (! empty($row['href']))
                        <a class="hover:underline focus:underline" href="{{ $row['href'] }}">{{ $row['label'] }}</a>
                    @else
                        {{ $row['label'] }}
                    @endif
                </span>
                <span class="relative flex h-6 items-center gap-2">
                    <span class="block h-4 shrink-0 rounded-e transition-opacity group-hover:opacity-80"
                          style="width: calc((100% - 6.5rem) * {{ $share }}); background: #2a78d6;"></span>
                    <span class="whitespace-nowrap text-gray-900">{{ $row['display'] ?? $row['value'] }}</span>
                    @if (! empty($row['detail']))
                        <span x-show="tip" x-cloak role="tooltip"
                              class="pointer-events-none absolute -top-8 left-0 z-10 whitespace-nowrap rounded bg-gray-900 px-2 py-1 text-xs text-white shadow">
                            <strong>{{ $row['display'] ?? $row['value'] }}</strong>
                            <span class="text-gray-300">· {{ $row['detail'] }}</span>
                        </span>
                    @endif
                </span>
            </li>
        @endforeach
    </ul>
    @if ($caption)
        <figcaption class="mt-3 text-xs text-gray-500">{{ $caption }}</figcaption>
    @endif
</figure>
