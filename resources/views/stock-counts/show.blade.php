@php
    use App\Enums\StockCountStatus as S;
    use App\Support\Format;

    $canEdit = auth()->user()->can('update', $count);
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$count->reference.($count->scope_note ? ' · '.$count->scope_note : '')"
                       :subtitle="$count->site->code.' · '.$count->site->name.' · '.__('created :when by :who', ['when' => Format::datetime($count->created_at), 'who' => $count->creator->name])">
            <x-count-status :status="$count->status" class="text-sm" />
            <x-export-link />
        </x-page-header>
    </x-slot>

    <x-page>
        @error('count')
            <div class="rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</div>
        @enderror

        @if ($count->status === S::Posted)
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                {{ __('Posted :when by :who. A posted count cannot be changed; correct mistakes with an adjustment.', ['when' => Format::datetime($count->posted_at), 'who' => $count->poster->name]) }}
            </div>
        @elseif ($count->status === S::Cancelled)
            <div class="rounded-md bg-gray-50 border border-gray-200 px-4 py-3 text-sm text-gray-700">{{ __('Cancelled. No stock was changed.') }}</div>
        @endif

        {{-- DRAFT: choose what to count --}}
        @if ($count->status === S::Draft && $canEdit)
            <section class="card card-body space-y-4" x-data="{ by: @js(old('by', 'due')) }">
                <h3 class="card-title">{{ __('Add items to count') }}</h3>
                <form method="POST" action="{{ route('stock-counts.lines.store', $count) }}" class="grid gap-3 sm:grid-cols-12 items-end">
                    @csrf
                    <div class="sm:col-span-3">
                        <label class="block text-xs font-medium text-gray-600" for="by">{{ __('Add by') }}</label>
                        <select name="by" id="by" x-model="by" class="form-input mt-1">
                            <option value="due">{{ __('Due for counting (:n)', ['n' => $dueCount]) }}</option>
                            <option value="category">{{ __('Category') }}</option>
                            <option value="criticality">{{ __('Criticality class') }}</option>
                            <option value="kanban">{{ __('Kanban items') }}</option>
                            <option value="item">{{ __('Single item') }}</option>
                        </select>
                    </div>
                    <div class="sm:col-span-7">
                        <div x-show="by === 'category'" x-cloak>
                            <select name="category_id" class="form-input" :disabled="by !== 'category'" aria-label="{{ __('Category') }}">
                                @foreach ($categories as $top)
                                    <option value="{{ $top->id }}">{{ $top->name }} {{ __('(all)') }}</option>
                                    @foreach ($top->children as $child)
                                        <option value="{{ $child->id }}">&nbsp;&nbsp;{{ $child->name }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                        </div>
                        <div x-show="by === 'criticality'" x-cloak>
                            <select name="criticality" class="form-input" :disabled="by !== 'criticality'" aria-label="{{ __('Criticality') }}">
                                <option value="A">{{ __('Class A') }}</option>
                                <option value="B">{{ __('Class B') }}</option>
                                <option value="C">{{ __('Class C') }}</option>
                            </select>
                        </div>
                        <div x-show="by === 'item'" x-cloak>
                            <x-item-picker name="item_id" />
                        </div>
                        <p x-show="by === 'due'" class="text-sm text-gray-500">{{ __('Items whose last count is older than the suggested frequency, or never counted.') }}</p>
                        <p x-show="by === 'kanban'" x-cloak class="text-sm text-gray-500">{{ __('Every kanban item at this site.') }}</p>
                        <x-input-error class="mt-1" :messages="$errors->get('item_id')" />
                    </div>
                    <div class="sm:col-span-2">
                        <button class="btn-primary w-full">{{ __('Add') }}</button>
                    </div>
                </form>
            </section>
        @endif

        {{-- Lines: count sheet while counting, a plain list otherwise --}}
        <section class="card">
            <form method="POST" action="{{ route('stock-counts.counts', $count) }}" x-data="{ showExpected: false }">
                @csrf @method('PUT')
                <div class="card-body pb-2 flex flex-wrap items-center justify-between gap-3">
                    <h3 class="card-title">
                        {{ __('Items') }} <span class="font-normal text-gray-400">({{ $lines->count() }})</span>
                    </h3>
                    @if ($count->status === S::Counting)
                        <label class="inline-flex items-center gap-1.5 text-sm text-gray-600">
                            <input type="checkbox" x-model="showExpected" class="form-checkbox">
                            {{ __('Show expected quantities') }}
                        </label>
                    @endif
                </div>

                @if ($count->status === S::Counting)
                    <p class="px-4 sm:px-6 pb-2 text-sm text-gray-500">{{ __('Sorted by bin. Enter what is on the shelf, 0 if it is empty; leave a line blank if it was not counted.') }}</p>
                @endif
                @error('lines.*')
                    <p class="px-4 sm:px-6 pb-2 text-sm text-red-600">{{ $message }}</p>
                @enderror

                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('Bin') }}</th>
                                <th>{{ __('Item') }}</th>
                                @if ($count->status === S::Counting)
                                    <th class="num" x-show="showExpected" x-cloak>{{ __('Expected') }}</th>
                                    <th class="num">{{ __('Counted') }}</th>
                                    <th>{{ __('Note') }}</th>
                                @elseif ($count->status === S::Draft)
                                    <th class="num">{{ __('Expected now') }}</th>
                                    <th>{{ __('Last counted') }}</th>
                                    <th></th>
                                @else
                                    <th class="num">{{ __('Expected') }}</th>
                                    <th class="num">{{ __('Counted') }}</th>
                                    <th>{{ __('Note') }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($lines as $line)
                                @php($stock = $stocks->get($line->item_id))
                                <tr>
                                    <td class="font-mono text-gray-700 whitespace-nowrap">{{ $stock?->bin ?? '—' }}</td>
                                    <td>
                                        <span class="font-mono">{{ $line->item->sku }}</span>
                                        <span class="text-gray-700">{{ $line->item->name }}</span>
                                        <span class="text-xs text-gray-500">· {{ $line->item->uom }}</span>
                                    </td>
                                    @if ($count->status === S::Counting)
                                        <td class="num text-gray-500" x-show="showExpected" x-cloak>{{ Format::qty($line->qty_expected) }}</td>
                                        <td class="num">
                                            <input name="lines[{{ $line->id }}][qty]" inputmode="decimal" autocomplete="off"
                                                   value="{{ old("lines.{$line->id}.qty", $line->qty_counted !== null ? Format::qty($line->qty_counted) : '') }}"
                                                   aria-label="{{ __('Counted quantity of :sku', ['sku' => $line->item->sku]) }}"
                                                   class="form-input w-24 text-right @error("lines.{$line->id}.qty") border-red-500 @enderror">
                                        </td>
                                        <td>
                                            <input name="lines[{{ $line->id }}][note]" maxlength="255" value="{{ old("lines.{$line->id}.note", $line->note) }}"
                                                   aria-label="{{ __('Note for :sku', ['sku' => $line->item->sku]) }}" class="form-input w-56">
                                        </td>
                                    @elseif ($count->status === S::Draft)
                                        <td class="num">{{ Format::qty($stock?->qty ?? 0) }}</td>
                                        <td class="text-gray-600">{{ $stock?->last_counted_at ? Format::date($stock->last_counted_at) : __('never') }}</td>
                                        <td class="text-right">
                                            @if ($canEdit)
                                                <button form="remove-{{ $line->id }}" class="btn-link-danger">{{ __('Remove') }}</button>
                                            @endif
                                        </td>
                                    @else
                                        <td class="num text-gray-600">{{ Format::qty($line->qty_expected) }}</td>
                                        <td class="num">{{ $line->qty_counted !== null ? Format::qty($line->qty_counted) : __('not counted') }}</td>
                                        <td class="text-gray-600">{{ $line->note }}</td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-6 text-center text-gray-500">{{ __('No items on this count yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($count->status === S::Counting && $canEdit)
                    <div class="card-body border-t border-gray-100 flex flex-wrap justify-end gap-2">
                        <button class="btn-secondary">{{ __('Save counts') }}</button>
                        <button name="review" value="1" class="btn-primary">{{ __('Save and review') }}</button>
                    </div>
                @endif
            </form>

            @if ($count->status === S::Draft && $canEdit)
                @foreach ($lines as $line)
                    <form id="remove-{{ $line->id }}" method="POST" action="{{ route('stock-count-lines.destroy', $line) }}" class="hidden">@csrf @method('DELETE')</form>
                @endforeach
            @endif
        </section>

        @if ($canEdit && in_array($count->status, [S::Draft, S::Counting], true))
            <section class="flex flex-wrap items-center justify-between gap-3">
                <form method="POST" action="{{ route('stock-counts.cancel', $count) }}"
                      onsubmit="return confirm(@js(__('Cancel this count? Nothing will be posted.')))">
                    @csrf
                    <button class="btn-link-danger">{{ __('Cancel count') }}</button>
                </form>
                @if ($count->status === S::Draft)
                    <form method="POST" action="{{ route('stock-counts.start', $count) }}">
                        @csrf
                        <button class="btn-primary" @disabled($lines->isEmpty())>{{ __('Start counting') }}</button>
                    </form>
                @endif
            </section>
        @endif

        @if ($adjustments)
            <section class="card" id="history">
                <div class="card-body pb-2"><h3 class="card-title">{{ __('Adjustments posted') }}</h3></div>
                @include('stock._ledger', ['transactions' => $adjustments, 'showItem' => true])
            </section>
        @endif
    </x-page>
</x-app-layout>
