@php
    use App\Support\Format;

    $alertTotal = collect($alerts)->except('due')->sum('total');
    $siteCodes = ! $site;
    $coveragePct = $coverage['with_minimum'] ? round($coverage['below'] / $coverage['with_minimum'] * 100) : 0;
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Dashboard')" :subtitle="$site ? $site->code.' · '.$site->name : __('All sites')" />
    </x-slot>

    <x-page>
        {{-- Alert panel (SPEC 8) --}}
        <section aria-labelledby="alerts-title" class="space-y-3">
            <h3 id="alerts-title" class="sr-only">{{ __('Alerts') }}</h3>

            @if ($alertTotal === 0)
                <div class="card card-body flex items-center gap-3 text-sm text-gray-700">
                    <span class="badge-green">✓</span>
                    {{ __('Nothing needs attention: no shortages, no late or stalled orders.') }}
                </div>
            @endif

            <div class="grid gap-4 lg:grid-cols-2">
                @foreach ([
                    'out' => [__('Out of stock'), __('Minimum set, nothing left.'), 'badge-red'],
                    'below' => [__('Below minimum'), __('Class A first.'), 'badge-red'],
                    'kanban' => [__('Kanban: refill'), __('One bin or less left.'), 'badge-amber'],
                ] as $key => [$title, $hint, $badge])
                    @continue($alerts[$key]['total'] === 0)
                    <section class="card">
                        <header class="card-body pb-2 flex items-baseline justify-between gap-2">
                            <h4 class="card-title"><span class="{{ $badge }} me-1.5">{{ $alerts[$key]['total'] }}</span>{{ $title }}</h4>
                            <span class="text-xs text-gray-500">{{ $hint }}</span>
                        </header>
                        <table class="table">
                            <tbody>
                                @foreach ($alerts[$key]['rows'] as $stock)
                                    @php($ordered = $onOrder[$stock->item_id][$stock->site_id] ?? null)
                                    <tr>
                                        <td class="w-8">{{ $stock->item->criticality?->value }}</td>
                                        <td>
                                            <a class="link font-mono" href="{{ route('items.show', $stock->item) }}">{{ $stock->item->sku }}</a>
                                            <span class="text-gray-700">{{ $stock->item->name }}</span>
                                            @if ($siteCodes)<span class="text-xs text-gray-500">· {{ $stock->site->code }}</span>@endif
                                        </td>
                                        <td class="num whitespace-nowrap">
                                            {{ Format::qty($stock->qty) }} / {{ Format::qty($stock->is_kanban ? $stock->bin_qty : $stock->min_level) }}
                                            <span class="text-gray-500">{{ $stock->item->uom }}</span>
                                        </td>
                                        <td class="num whitespace-nowrap text-xs text-indigo-700">{{ $ordered ? __(':qty on order', ['qty' => Format::qty($ordered)]) : '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if ($alerts[$key]['total'] > $alerts[$key]['rows']->count())
                            <a class="block card-body pt-2 text-sm link" href="{{ route($key === 'kanban' ? 'kanban.index' : 'stock.index', $key === 'kanban' ? [] : ['below' => 1]) }}">
                                {{ __('and :n more', ['n' => $alerts[$key]['total'] - $alerts[$key]['rows']->count()]) }} →
                            </a>
                        @endif
                    </section>
                @endforeach

                @foreach ([
                    'late' => [__('Orders past their ETA'), __('Nothing received yet.')],
                    'unconfirmed' => [__('Orders not confirmed'), __('Sent, no reply from the supplier.')],
                    'stalled' => [__('Partly received for over 30 days'), __('Close the rest short or chase it.')],
                ] as $key => [$title, $hint])
                    @continue($alerts[$key]['total'] === 0)
                    <section class="card">
                        <header class="card-body pb-2 flex items-baseline justify-between gap-2">
                            <h4 class="card-title"><span class="badge-amber me-1.5">{{ $alerts[$key]['total'] }}</span>{{ $title }}</h4>
                            <span class="text-xs text-gray-500">{{ $hint }}</span>
                        </header>
                        <table class="table">
                            <tbody>
                                @foreach ($alerts[$key]['rows'] as $order)
                                    <tr>
                                        <td class="font-mono whitespace-nowrap"><a class="link" href="{{ route('purchase-orders.show', $order) }}">{{ $order->number }}</a></td>
                                        <td>{{ $order->supplier->name }} @if ($siteCodes)<span class="text-xs text-gray-500">· {{ $order->site->code }}</span>@endif</td>
                                        <td class="num whitespace-nowrap text-gray-700">
                                            @if ($key === 'late')
                                                {{ __('ETA :date', ['date' => Format::date($order->eta)]) }}
                                            @elseif ($key === 'unconfirmed')
                                                {{ $order->ordered_at ? __('sent :days', ['days' => $order->ordered_at->diffForHumans(['parts' => 1])]) : '' }}
                                            @else
                                                <x-po-status :status="$order->status" />
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if ($alerts[$key]['total'] > $alerts[$key]['rows']->count())
                            <a class="block card-body pt-2 text-sm link" href="{{ route('purchase-orders.index', ['status' => 'open']) }}">
                                {{ __('and :n more', ['n' => $alerts[$key]['total'] - $alerts[$key]['rows']->count()]) }} →
                            </a>
                        @endif
                    </section>
                @endforeach
            </div>

            @if ($alerts['due']['total'] > 0)
                <div class="rounded-md bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800 flex flex-wrap items-center justify-between gap-2">
                    <span>{{ trans_choice('{1} 1 item is due for counting.|[2,*] :count items are due for counting.', $alerts['due']['total'], ['count' => $alerts['due']['total']]) }}</span>
                    @can('viewAny', App\Models\StockCount::class)
                        <a class="link" href="{{ route('stock-counts.index') }}">{{ __('Plan a count') }} →</a>
                    @endcan
                </div>
            @endif
        </section>

        {{-- Figures (SPEC 8) --}}
        <section aria-labelledby="figures-title" class="space-y-4">
            <h3 id="figures-title" class="sr-only">{{ __('Figures') }}</h3>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="card card-body sm:col-span-2 lg:col-span-1">
                    <div class="text-sm text-gray-500">{{ __('Stock value') }}</div>
                    <div class="mt-1 text-5xl font-semibold text-gray-900">${{ Format::money($totalValue) }}</div>
                    <div class="mt-1 text-xs text-gray-500">{{ __('Net supplier prices; freight and duty excluded.') }}</div>
                </div>

                <div class="card card-body">
                    <div class="text-sm text-gray-500">{{ __('Consumption in :month so far', ['month' => $consumption['month']]) }}</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900">${{ Format::money($consumption['current']) }}</div>
                    <div class="mt-1 text-xs text-gray-600">
                        {{ __(':month in total: $:value', ['month' => $consumption['previous_month'], 'value' => Format::money($consumption['previous'])]) }}
                    </div>
                </div>

                <div class="card card-body">
                    <div class="text-sm text-gray-500">{{ __('Below minimum') }}</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900">
                        {{ $coverage['below'] }} <span class="text-base font-normal text-gray-500">{{ __('of :n with a minimum', ['n' => $coverage['with_minimum']]) }}</span>
                    </div>
                    <div class="mt-2 h-2 rounded-full" style="background: #cde2fb" role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $coveragePct }}"
                         aria-label="{{ __(':pct% of items with a minimum are below it', ['pct' => $coveragePct]) }}">
                        <div class="h-2 rounded-full" style="width: {{ $coveragePct }}%; background: #2a78d6"></div>
                    </div>
                </div>

                <div class="card card-body">
                    <div class="text-sm text-gray-500">{{ __('No movement in 12 months') }}</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $dormant['count'] }} <span class="text-base font-normal text-gray-500">{{ __('items') }}</span></div>
                    <div class="mt-1 text-xs text-gray-600">{{ __(':value USD tied up', ['value' => Format::money($dormant['value'])]) }}</div>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <section class="card card-body">
                    <h4 class="card-title">{{ __('Stock value by category') }}</h4>
                    <p class="mb-4 text-xs text-gray-500">{{ __('USD at moving average cost') }}</p>
                    @if ($byCategory->isEmpty())
                        <p class="text-sm text-gray-500">{{ __('No stock recorded yet.') }}</p>
                    @else
                        <x-bar-list :label="__('Stock value by category')" :rows="$byCategory->map(fn ($row) => [
                            'label' => $row->name,
                            'value' => $row->value,
                            'display' => '$'.Format::money($row->value),
                            'detail' => round((float) $row->value / max((float) $totalValue, 1) * 100).'% '.__('of stock value'),
                        ])->all()" />
                    @endif
                </section>

                <section class="card card-body">
                    <h4 class="card-title">{{ __('Top machines by consumption') }}</h4>
                    <p class="mb-4 text-xs text-gray-500">{{ __('USD issued over the last 90 days') }}</p>
                    @if ($topMachines->isEmpty())
                        <p class="text-sm text-gray-500">{{ __('Nothing issued to machines in the last 90 days.') }}</p>
                    @else
                        <x-bar-list :label="__('Top machines by consumption')" :rows="$topMachines->map(fn ($row) => [
                            'label' => $row->sku.' · '.$row->name.' '.$row->revision.($siteCodes ? ' · '.$row->site : ''),
                            'value' => $row->value,
                            'display' => '$'.Format::money($row->value),
                            'detail' => trans_choice('{1} 1 issue|[2,*] :count issues', $row->issues, ['count' => $row->issues]),
                            'href' => route('machines.show', $row->machine_id),
                        ])->all()"
                            :caption="__('These figures are what will correct the minimum levels, which were set by guesswork.')" />
                    @endif
                </section>
            </div>

            @if ($dormant['rows']->isNotEmpty())
                <section class="card">
                    <div class="card-body pb-2"><h4 class="card-title">{{ __('Highest value with no movement in 12 months') }}</h4></div>
                    <table class="table">
                        <tbody>
                            @foreach ($dormant['rows'] as $stock)
                                <tr>
                                    <td><a class="link font-mono" href="{{ route('items.show', $stock->item) }}">{{ $stock->item->sku }}</a> {{ $stock->item->name }}</td>
                                    @if ($siteCodes)<td>{{ $stock->site->code }}</td>@endif
                                    <td class="num">{{ Format::qty($stock->qty) }} {{ $stock->item->uom }}</td>
                                    <td class="num">${{ Format::money($stock->value()) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @endif
        </section>
    </x-page>
</x-app-layout>
