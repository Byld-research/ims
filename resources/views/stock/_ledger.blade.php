{{--
    Ledger rows (stock_transactions), newest first. Read-only by design: corrections are new rows.
    Expects: $transactions (paginator), $showItem (bool), optional $uom when all rows share an item.
--}}
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>{{ __('When') }}</th>
                @if ($showItem)<th>{{ __('Item') }}</th>@endif
                <th>{{ __('Site') }}</th>
                <th>{{ __('Movement') }}</th>
                <th>{{ __('Reference') }}</th>
                <th class="num">{{ __('Qty') }}</th>
                <th class="num">{{ __('Unit cost') }}</th>
                <th class="num">{{ __('Value') }}</th>
                <th class="num">{{ __('Stock after') }}</th>
                <th>{{ __('By') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($transactions as $txn)
                @php($incoming = bccomp($txn->qty_delta, '0', 3) > 0)
                <tr>
                    <td class="whitespace-nowrap text-gray-600" title="{{ $txn->created_at->toIso8601String() }}">{{ \App\Support\Format::datetime($txn->created_at) }}</td>
                    @if ($showItem)
                        <td><a class="link font-mono" href="{{ route('items.show', $txn->item) }}">{{ $txn->item->sku }}</a> <span class="text-gray-700">{{ $txn->item->name }}</span></td>
                    @endif
                    <td>{{ $txn->site->code }}</td>
                    <td class="whitespace-nowrap">{{ $txn->type->label() }}</td>
                    <td class="text-gray-700">
                        @switch($txn->type)
                            @case(App\Enums\TransactionType::IssueMachine)
                                <a class="link font-mono" href="{{ route('machines.show', $txn->machine) }}">{{ $txn->machine->sku }}</a>
                                @break
                            @case(App\Enums\TransactionType::TransferIn)
                                {{ __('from :site', ['site' => $txn->counterSite->code]) }}
                                @break
                            @case(App\Enums\TransactionType::TransferOut)
                                {{ __('to :site', ['site' => $txn->counterSite->code]) }}
                                @break
                            @case(App\Enums\TransactionType::Receipt)
                                {{ $txn->purchaseOrderLine?->purchaseOrder?->number }}
                                @break
                        @endswitch
                        @if ($txn->reasonCode)
                            <span class="badge-gray">{{ $txn->reasonCode->label }}</span>
                        @endif
                        @if ($txn->note)
                            <span class="block text-xs text-gray-500">{{ $txn->note }}</span>
                        @endif
                    </td>
                    <td class="num font-semibold {{ $incoming ? 'text-green-700' : 'text-red-700' }}">
                        {{ $incoming ? '+' : '−' }}{{ \App\Support\Format::qty(ltrim($txn->qty_delta, '-')) }}
                    </td>
                    <td class="num text-gray-600">{{ \App\Support\Format::money($txn->unit_cost) }}</td>
                    <td class="num text-gray-600">{{ \App\Support\Format::money($txn->value) }}</td>
                    <td class="num">{{ \App\Support\Format::qty($txn->qty_after) }} {{ $uom ?? '' }}</td>
                    <td class="text-gray-600 whitespace-nowrap">{{ $txn->user->name }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $showItem ? 10 : 9 }}" class="py-6 text-center text-gray-500">{{ __('No movements yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if ($transactions->hasPages())
    <div class="card-body pt-2">{{ $transactions->fragment('history')->links() }}</div>
@endif
