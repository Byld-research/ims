@php
    use App\Support\Format;
    $sections = $digest->sections;
    $allSites = $digest->site === null;
@endphp
<x-mail::message>
# {{ $allSites ? __('All sites') : $digest->site->code.' · '.$digest->site->name }}

{{ __('Daily digest for :date: :summary.', ['date' => $date, 'summary' => $digest->summary()]) }}

@if ($sections['below']['total'])
## {{ __('Below minimum') }} ({{ $sections['below']['total'] }})

<x-mail::table>
| {{ __('Item') }} | @if ($allSites){{ __('Site') }} | @endif{{ __('Stock / min') }} | {{ __('On order') }} |
|:--|@if ($allSites):--|@endif--:|--:|
@foreach ($sections['below']['rows'] as $stock)
| {{ $stock->item->criticality?->value ? '['.$stock->item->criticality->value.'] ' : '' }}{{ $stock->item->sku }} {{ $stock->item->name }} | @if ($allSites){{ $stock->site->code }} | @endif{{ Format::qty($stock->qty) }} / {{ Format::qty($stock->min_level) }} {{ $stock->item->uom }} | {{ isset($digest->onOrder[$stock->item_id][$stock->site_id]) ? Format::qty($digest->onOrder[$stock->item_id][$stock->site_id]) : '' }} |
@endforeach
</x-mail::table>
@if ($sections['below']['total'] > $sections['below']['rows']->count())
{{ __('and :n more.', ['n' => $sections['below']['total'] - $sections['below']['rows']->count()]) }}
@endif
@endif

@if ($sections['kanban']['total'])
## {{ __('Kanban refills') }} ({{ $sections['kanban']['total'] }})

<x-mail::table>
| {{ __('Item') }} | @if ($allSites){{ __('Site') }} | @endif{{ __('Stock / bin') }} | {{ __('On order') }} |
|:--|@if ($allSites):--|@endif--:|--:|
@foreach ($sections['kanban']['rows'] as $stock)
| {{ $stock->item->sku }} {{ $stock->item->name }} | @if ($allSites){{ $stock->site->code }} | @endif{{ Format::qty($stock->qty) }} / {{ Format::qty($stock->bin_qty) }} {{ $stock->item->uom }} | {{ isset($digest->onOrder[$stock->item_id][$stock->site_id]) ? Format::qty($digest->onOrder[$stock->item_id][$stock->site_id]) : '' }} |
@endforeach
</x-mail::table>
@endif

@if ($sections['late']['total'])
## {{ __('Orders past their ETA, nothing received') }} ({{ $sections['late']['total'] }})

@foreach ($sections['late']['rows'] as $order)
- **{{ $order->number }}** {{ $order->supplier->name }}@if ($allSites) → {{ $order->site->code }}@endif, {{ __('ETA :date', ['date' => Format::date($order->eta)]) }}
@endforeach
@endif

@if ($sections['unconfirmed']['total'])
## {{ __('Orders never confirmed by the supplier') }} ({{ $sections['unconfirmed']['total'] }})

@foreach ($sections['unconfirmed']['rows'] as $order)
- **{{ $order->number }}** {{ $order->supplier->name }}@if ($allSites) → {{ $order->site->code }}@endif, {{ __('sent :date', ['date' => Format::date($order->ordered_at)]) }}
@endforeach
@endif

<x-mail::button :url="url('/')">
{{ __('Open the dashboard') }}
</x-mail::button>

<small>{{ __('You get this because the daily digest is on in your profile. It is sent only when something needs attention.') }}</small>
</x-mail::message>
