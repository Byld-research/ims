@props(['status'])

@php
    $class = match ($status) {
        App\Enums\StockCountStatus::Draft => 'badge-gray',
        App\Enums\StockCountStatus::Counting => 'badge-amber',
        App\Enums\StockCountStatus::Posted => 'badge-green',
        App\Enums\StockCountStatus::Cancelled => 'badge-gray',
    };
@endphp

<span {{ $attributes->merge(['class' => $class]) }}>{{ ucfirst(strtolower($status->name)) }}</span>
