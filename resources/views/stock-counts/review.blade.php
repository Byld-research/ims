@php use App\Support\Format; @endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Review and post · :ref', ['ref' => $count->reference])" :subtitle="$count->site->code.' · '.$count->site->name" />
    </x-slot>

    <x-page>
        @if ($errors->any())
            <div class="rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="card card-body grid gap-4 sm:grid-cols-4 text-sm">
            <div><div class="text-gray-500">{{ __('Counted lines') }}</div><div class="text-xl font-semibold">{{ $counted->count() }}</div></div>
            <div><div class="text-gray-500">{{ __('Will be adjusted') }}</div><div class="text-xl font-semibold">{{ $adjusting->count() }}</div></div>
            <div>
                <div class="text-gray-500">{{ __('Value of adjustments') }}</div>
                <div class="text-xl font-semibold tabular-nums">{{ Format::money($adjusting->reduce(fn ($sum, $row) => bcadd($sum, $row['value'], 4), '0')) }} USD</div>
            </div>
            <div><div class="text-gray-500">{{ __('Not counted, skipped') }}</div><div class="text-xl font-semibold {{ $skipped ? 'text-amber-700' : '' }}">{{ $skipped }}</div></div>
        </section>

        @if ($moved)
            <div class="rounded-md bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                {{ trans_choice('{1} Stock of 1 item moved since it was added to the count.|[2,*] Stock of :count items moved since they were added to the count.', $moved, ['count' => $moved]) }}
                {{ __('The adjustment is taken against stock as it is now, so issues and receipts made while counting are not lost. Check those lines: was the count taken before or after the movement?') }}
            </div>
        @endif

        <form method="POST" action="{{ route('stock-counts.post', $count) }}" class="card">
            @csrf
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Item') }}</th>
                            <th class="num">{{ __('Expected when added') }}</th>
                            <th class="num">{{ __('In stock now') }}</th>
                            <th class="num">{{ __('Counted') }}</th>
                            <th class="num">{{ __('Adjustment') }}</th>
                            <th class="num">{{ __('Value') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($preview as $row)
                            @php($line = $row['line'])
                            <tr @class(['bg-amber-50/60' => $row['moved'], 'text-gray-400' => $row['difference'] === null])>
                                <td>
                                    <span class="font-mono">{{ $line->item->sku }}</span> {{ $line->item->name }}
                                    @if ($row['needs_cost'])
                                        <div class="mt-1 flex items-center gap-2 text-xs text-gray-700">
                                            <label for="cost-{{ $line->id }}">{{ __('No cost at this site yet; unit cost (USD):') }}</label>
                                            <input id="cost-{{ $line->id }}" name="costs[{{ $line->id }}]" inputmode="decimal" required
                                                   value="{{ old('costs.'.$line->id) }}" class="form-input w-28 py-1 text-right @error('costs.'.$line->id) border-red-500 @enderror">
                                        </div>
                                    @endif
                                </td>
                                <td class="num">{{ Format::qty($line->qty_expected) }}</td>
                                <td class="num {{ $row['moved'] ? 'font-semibold text-amber-800' : '' }}">{{ Format::qty($row['live']) }}</td>
                                <td class="num">{{ $row['difference'] === null ? __('not counted') : Format::qty($line->qty_counted) }}</td>
                                <td class="num font-semibold">
                                    @if ($row['difference'] !== null && bccomp($row['difference'], '0', 3) !== 0)
                                        <span class="{{ bccomp($row['difference'], '0', 3) > 0 ? 'text-green-700' : 'text-red-700' }}">
                                            {{ bccomp($row['difference'], '0', 3) > 0 ? '+' : '−' }}{{ Format::qty(ltrim($row['difference'], '-')) }}
                                        </span>
                                    @elseif ($row['difference'] !== null)
                                        <span class="text-gray-400">{{ __('none') }}</span>
                                    @endif
                                </td>
                                <td class="num text-gray-600">{{ $row['value'] !== null && bccomp($row['value'], '0', 4) !== 0 ? Format::money($row['value']) : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-body border-t border-gray-100 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-gray-600 max-w-xl">
                    {{ __('Posting writes one COUNT adjustment per difference and marks every counted item as counted today. It cannot be undone; mistakes are corrected with a new adjustment.') }}
                </p>
                <div class="flex gap-2">
                    <a href="{{ route('stock-counts.show', $count) }}" class="btn-secondary">{{ __('Back to the count sheet') }}</a>
                    <button class="btn-primary" @disabled($counted->isEmpty())>{{ __('Post count') }}</button>
                </div>
            </div>
        </form>
    </x-page>
</x-app-layout>
