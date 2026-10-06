@php
    use App\Support\Format;

    $siteCodes = ! $site;
    $orderCount = $alerts['late']['total'] + $alerts['unconfirmed']['total'] + $alerts['stalled']['total'];
    $coveragePct = $coverage['with_minimum'] ? round($coverage['below'] / $coverage['with_minimum'] * 100) : 0;
    $reasons = [
        'late' => __('Past ETA, nothing received'),
        'stalled' => __('Partly received > 30 days'),
        'unconfirmed' => __('Not confirmed by supplier'),
    ];
    // The status bar: what needs attention, worst first. A zero tile turns quiet and green.
    $tiles = [
        ['key' => 'out', 'label' => __('Out of stock'), 'count' => $alerts['out']['total'], 'severity' => 'critical', 'href' => '#act'],
        ['key' => 'below', 'label' => __('Below minimum'), 'count' => $alerts['below']['total'], 'severity' => 'serious', 'href' => '#act'],
        ['key' => 'kanban', 'label' => __('Two-bin refill'), 'count' => $alerts['kanban']['total'], 'severity' => 'warning', 'href' => '#act'],
        ['key' => 'orders', 'label' => __('Orders to chase'), 'count' => $orderCount, 'severity' => 'serious', 'href' => '#orders'],
        ['key' => 'due', 'label' => __('Due for counting'), 'count' => $alerts['due']['total'], 'severity' => 'warning',
            'href' => auth()->user()->can('viewAny', App\Models\StockCount::class) ? route('stock-counts.index') : null],
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Dashboard')" :subtitle="$site ? $site->code.' · '.$site->name : __('All sites')" />
    </x-slot>

    <x-page>
        {{-- 1. Status bar --}}
        <section aria-label="{{ __('What needs attention') }}" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($tiles as $tile)
                @php
                    $active = $tile['count'] > 0;
                @endphp
                <a @if ($tile['href']) href="{{ $tile['href'] }}" @endif
                   @class(['group relative overflow-hidden rounded-lg border bg-white p-4 shadow-sm transition',
                       'hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-500' => $tile['href'],
                       'border-transparent' => $active, 'border-gray-200' => ! $active])
                   @if ($active) style="background: var(--status-{{ $tile['severity'] }}-tint); box-shadow: inset 4px 0 0 var(--status-{{ $tile['severity'] }});" @endif>
                    <div class="flex items-center gap-2 text-sm font-medium {{ $active ? 'text-gray-900' : 'text-gray-500' }}">
                        <x-status-icon :severity="$active ? $tile['severity'] : 'good'" />
                        {{ $tile['label'] }}
                    </div>
                    <div class="mt-2 text-3xl font-bold {{ $active ? 'text-gray-900' : 'text-gray-400' }}">{{ $tile['count'] }}</div>
                    <div class="mt-0.5 text-xs {{ $active ? 'text-gray-700' : 'text-gray-400' }}">
                        {{ $active ? ($tile['href'] ? __('Review') . ' →' : '') : __('All clear') }}
                    </div>
                </a>
            @endforeach
        </section>

        <div class="grid gap-6 xl:grid-cols-3">
            {{-- 2. Stock to act on --}}
            <section id="act" class="card xl:col-span-2 scroll-mt-24 overflow-hidden">
                <header class="card-body pb-3 flex flex-wrap items-baseline justify-between gap-2 border-b border-gray-100">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">{{ __('Stock to act on') }}</h3>
                        <p class="text-xs text-gray-500">{{ __('Worst first: out of stock, then class A, then least left. The mark on each bar is the minimum (or one bin of a two-bin item).') }}</p>
                    </div>
                    @if ($attention['total'] > $attention['rows']->count())
                        <a class="link text-sm" href="{{ route('stock.index', ['below' => 1]) }}">{{ __('All :n', ['n' => $attention['total']]) }} →</a>
                    @endif
                </header>

                @if ($attention['rows']->isEmpty())
                    <div class="card-body flex items-center gap-3 text-sm text-gray-700">
                        <x-status-icon severity="good" />
                        {{ __('Every item with a minimum is above it. Nothing to reorder.') }}
                    </div>
                @else
                    {{-- Columns from md up; on a phone each item stacks: name and status, the bar, then order and usage. --}}
                    <div class="hidden md:grid md:grid-cols-[minmax(0,2.4fr)_6.5rem_minmax(9rem,1.4fr)_5rem_7rem] gap-4 bg-gray-50 px-5 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <span>{{ __('Item') }}</span><span>{{ __('Status') }}</span><span>{{ __('Level') }}</span>
                        <span class="text-right">{{ __('On order') }}</span><span>{{ __('Used, 12 weeks') }}</span>
                    </div>
                    <ul role="list" class="divide-y divide-gray-100">
                        @foreach ($attention['rows'] as $row)
                            @php
                                $stock = $row['stock'];
                                $key = $stock->item_id.':'.$stock->site_id;
                                $ordered = $onOrder[$stock->item_id][$stock->site_id] ?? null;
                                $threshold = $stock->is_kanban ? $stock->bin_qty : $stock->min_level;
                                $chip = match ($row['severity']) {
                                    'critical' => __('Out'),
                                    'serious' => __('Low · A'),
                                    default => $stock->is_kanban ? __('Refill') : __('Low'),
                                };
                                $details = array_filter([
                                    $stock->item->criticality ? __('Class :c', ['c' => $stock->item->criticality->value]) : null,
                                    $stock->bin ? __('location :b', ['b' => $stock->bin]) : null,
                                    $siteCodes ? $stock->site->code : null,
                                ]);
                            @endphp
                            <li class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-2 px-5 py-3 md:grid-cols-[minmax(0,2.4fr)_6.5rem_minmax(9rem,1.4fr)_5rem_7rem]"
                                style="box-shadow: inset 4px 0 0 var(--status-{{ $row['severity'] }})">
                                <div class="min-w-0">
                                    <a class="link font-mono text-sm" href="{{ route('items.show', $stock->item) }}">{{ $stock->item->sku }}</a>
                                    <span class="text-sm text-gray-900">{{ $stock->item->name }}</span>
                                    <span class="block text-xs text-gray-500">{{ implode(' · ', $details) }}</span>
                                </div>
                                <div><x-status-chip :severity="$row['severity']" :label="$chip" /></div>
                                <div class="col-span-2 md:col-span-1">
                                    <div class="flex items-baseline justify-between text-xs text-gray-700">
                                        <span><span class="text-sm font-semibold text-gray-900">{{ Format::qty($stock->qty) }}</span> {{ $stock->item->uom }}</span>
                                        <span>{{ $stock->is_kanban ? __('bin :n', ['n' => Format::qty($threshold)]) : __('min :n', ['n' => Format::qty($threshold)]) }}</span>
                                    </div>
                                    <x-level-meter class="mt-1" :qty="$stock->qty" :threshold="$threshold" :severity="$row['severity']" :uom="$stock->item->uom" :kanban="$stock->is_kanban" />
                                </div>
                                <div class="text-xs text-gray-600 md:text-right">
                                    <span class="md:hidden">{{ __('On order') }}:</span>
                                    @if ($ordered)
                                        <span class="badge-indigo">{{ Format::qty($ordered) }}</span>
                                    @else
                                        {{ __('none') }}
                                    @endif
                                </div>
                                <div class="justify-self-end md:justify-self-start">
                                    <x-sparkline :values="$usage[$key] ?? []" :label="__('Used per week, last 12 weeks')" />
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- 3. Orders to chase --}}
            <section id="orders" class="card scroll-mt-24 overflow-hidden">
                <header class="card-body pb-3 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('Orders to chase') }}</h3>
                    <p class="text-xs text-gray-500">{{ __('Late, unconfirmed or stuck half-delivered.') }}</p>
                </header>
                @forelse ($orders as $row)
                    @php
                        $order = $row['order'];
                    @endphp
                    <a href="{{ route('purchase-orders.show', $order) }}" class="block border-b border-gray-100 px-4 py-3 hover:bg-gray-50 sm:px-6"
                       style="box-shadow: inset 4px 0 0 var(--status-{{ $row['severity'] }})">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-mono text-sm text-indigo-700">{{ $order->number }}</span>
                            <x-status-chip :severity="$row['severity']" :label="$reasons[$row['reason']]" />
                        </div>
                        <div class="mt-1 text-sm text-gray-900">{{ $order->supplier->name }} @if ($siteCodes)<span class="text-gray-500">→ {{ $order->site->code }}</span>@endif</div>
                        <div class="text-xs text-gray-600">
                            @if ($row['reason'] === 'late')
                                {{ __('ETA was :date (:ago)', ['date' => Format::date($order->eta), 'ago' => $order->eta->diffForHumans(['parts' => 1])]) }}
                            @elseif ($row['reason'] === 'unconfirmed')
                                {{ $order->ordered_at ? __('Sent :date', ['date' => Format::date($order->ordered_at)]) : '' }}
                            @else
                                <x-po-status :status="$order->status" />
                            @endif
                        </div>
                    </a>
                @empty
                    <div class="card-body flex items-center gap-3 text-sm text-gray-700">
                        <x-status-icon severity="good" />
                        {{ __('No late or stuck orders.') }}
                    </div>
                @endforelse
            </section>
        </div>

        {{-- 4. What is moving --}}
        <section class="card overflow-hidden">
            <header class="card-body pb-3 flex flex-wrap items-baseline justify-between gap-2 border-b border-gray-100">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('Most used in the last 30 days') }}</h3>
                    <p class="text-xs text-gray-500">{{ __('The items that are moving. Keep these above their minimum; a fast mover without a minimum is not being watched.') }}</p>
                </div>
            </header>
            @if ($movers->isEmpty())
                <p class="card-body text-sm text-gray-500">{{ __('Nothing issued in the last 30 days.') }}</p>
            @else
                <div class="hidden md:grid md:grid-cols-[minmax(0,2.4fr)_7rem_7rem_minmax(9rem,1.4fr)_8rem] gap-4 bg-gray-50 px-5 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <span>{{ __('Item') }}</span><span class="text-right">{{ __('Used, 30 days') }}</span><span>{{ __('Used, 12 weeks') }}</span>
                    <span>{{ __('Level') }}</span><span>{{ __('Watch') }}</span>
                </div>
                <ul role="list" class="divide-y divide-gray-100">
                    @foreach ($movers as $row)
                        @php
                            $stock = $row['stock'];
                            $hasLevel = $stock->is_kanban || bccomp($stock->min_level, '0', 3) > 0;
                            $threshold = $stock->is_kanban ? $stock->bin_qty : $stock->min_level;
                            $state = match (true) {
                                ! $hasLevel => ['warning', __('No minimum set')],
                                bccomp($stock->qty, '0', 3) === 0 => ['critical', __('Out')],
                                $stock->needsReplenishment() => [$stock->is_kanban ? 'warning' : 'serious', $stock->is_kanban ? __('Refill') : __('Low')],
                                default => ['good', __('OK')],
                            };
                        @endphp
                        <li class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-2 px-5 py-3 md:grid-cols-[minmax(0,2.4fr)_7rem_7rem_minmax(9rem,1.4fr)_8rem]"
                            @if ($state[0] !== 'good') style="box-shadow: inset 4px 0 0 var(--status-{{ $state[0] }})" @endif>
                            <div class="min-w-0">
                                <a class="link font-mono text-sm" href="{{ route('items.show', $stock->item) }}">{{ $stock->item->sku }}</a>
                                <span class="text-sm text-gray-900">{{ $stock->item->name }}</span>
                                @if ($siteCodes)<span class="block text-xs text-gray-500">{{ $stock->site->code }}</span>@endif
                            </div>
                            <div class="md:order-last">
                                <x-status-chip :severity="$state[0]" :label="$state[1]" />
                                @if (! $hasLevel && $site && auth()->user()->can('setLevels', [App\Models\Stock::class, $site]))
                                    <a class="mt-1 block text-xs link" href="{{ route('stock.levels', ['site' => $site->id, 'q' => $stock->item->sku]) }}">{{ __('Set a minimum') }}</a>
                                @endif
                            </div>
                            <div class="text-sm md:text-right">
                                <span class="font-semibold text-gray-900">{{ Format::qty($row['qty']) }}</span> {{ $stock->item->uom }}
                                <span class="text-xs text-gray-500 md:block">${{ Format::money($row['value']) }}</span>
                            </div>
                            <div class="justify-self-end md:justify-self-start">
                                <x-sparkline :values="$usage[$row['item_id'].':'.$row['site_id']] ?? []" :label="__('Used per week, last 12 weeks')" />
                            </div>
                            <div class="col-span-2 md:col-span-1">
                                <div class="flex items-baseline justify-between text-xs text-gray-700">
                                    <span><span class="text-sm font-semibold text-gray-900">{{ Format::qty($stock->qty) }}</span> {{ $stock->item->uom }} {{ __('in stock') }}</span>
                                    @if ($hasLevel)<span>{{ $stock->is_kanban ? __('bin :n', ['n' => Format::qty($threshold)]) : __('min :n', ['n' => Format::qty($threshold)]) }}</span>@endif
                                </div>
                                @if ($hasLevel)
                                    <x-level-meter class="mt-1" :qty="$stock->qty" :threshold="$threshold" :severity="$state[0] === 'good' ? 'ok' : $state[0]" :uom="$stock->item->uom" :kanban="$stock->is_kanban" />
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- 5. Overview: quieter, below the work --}}
        <section aria-labelledby="overview-title" class="space-y-4 pt-2">
            <h3 id="overview-title" class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ __('Overview') }}</h3>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="card card-body">
                    <div class="text-sm text-gray-500">{{ __('Stock value') }}</div>
                    <div class="mt-1 text-3xl font-semibold text-gray-900">${{ Format::money($totalValue) }}</div>
                    <div class="mt-1 text-xs text-gray-500">{{ __('Net supplier prices; freight and duty excluded.') }}</div>
                </div>
                <div class="card card-body">
                    <div class="text-sm text-gray-500">{{ __('Consumption in :month so far', ['month' => $consumption['month']]) }}</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900">${{ Format::money($consumption['current']) }}</div>
                    <div class="mt-1 text-xs text-gray-600">{{ __(':month in total: $:value', ['month' => $consumption['previous_month'], 'value' => Format::money($consumption['previous'])]) }}</div>
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
