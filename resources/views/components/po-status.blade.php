@props(['status'])

@php
    $class = match ($status) {
        App\Enums\PurchaseOrderStatus::Draft => 'badge-gray',
        App\Enums\PurchaseOrderStatus::Ordered, App\Enums\PurchaseOrderStatus::Confirmed => 'badge-indigo',
        App\Enums\PurchaseOrderStatus::Shipped, App\Enums\PurchaseOrderStatus::PartiallyReceived => 'badge-amber',
        App\Enums\PurchaseOrderStatus::Received => 'badge-green',
        App\Enums\PurchaseOrderStatus::Closed, App\Enums\PurchaseOrderStatus::Cancelled => 'badge-gray',
    };
@endphp

<span {{ $attributes->merge(['class' => $class]) }}>{{ $status->label() }}</span>
