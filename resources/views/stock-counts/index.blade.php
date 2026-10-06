<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Stock counts')" :subtitle="$site ? $site->code.' · '.$site->name : __('All sites')">
            <x-export-link />
            @if ($site ? auth()->user()->can('create', [App\Models\StockCount::class, $site]) : auth()->user()->isAdmin())
                <a href="{{ route('stock-counts.create') }}" class="btn-primary">{{ __('New count') }}</a>
            @endif
        </x-page-header>
    </x-slot>

    <x-page>
        @if ($dueCount)
            <div class="rounded-md bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                {{ trans_choice('{1} 1 item is due for counting|[2,*] :count items are due for counting', $dueCount, ['count' => $dueCount]) }}
                {{ __('(high criticality monthly; normal, low and two-bin items quarterly). Add them to a count with “Due for counting”.') }}
            </div>
        @endif

        <form method="GET" class="flex justify-end">
            <x-select name="status" :options="collect(App\Enums\StockCountStatus::cases())->mapWithKeys(fn ($s) => [$s->value => ucfirst(strtolower($s->name))])->all()"
                      :value="$filters['status'] ?? null" :placeholder="__('Any status')" class="w-48" onchange="this.form.submit()" />
        </form>

        <div class="card table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Reference') }}</th>
                        @unless ($site)<th>{{ __('Site') }}</th>@endunless
                        <th>{{ __('Scope') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="num">{{ __('Counted') }}</th>
                        <th>{{ __('Created') }}</th>
                        <th>{{ __('Posted') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($counts as $count)
                        <tr>
                            <td class="font-mono"><a class="link" href="{{ route('stock-counts.show', $count) }}">{{ $count->reference }}</a></td>
                            @unless ($site)<td>{{ $count->site->code }}</td>@endunless
                            <td>{{ $count->scope_note }}</td>
                            <td><x-count-status :status="$count->status" /></td>
                            <td class="num">{{ $count->counted_count }} / {{ $count->lines_count }}</td>
                            <td class="text-gray-600 whitespace-nowrap">{{ \App\Support\Format::datetime($count->created_at) }} · {{ $count->creator->name }}</td>
                            <td class="text-gray-600 whitespace-nowrap">{{ $count->posted_at ? \App\Support\Format::datetime($count->posted_at).' · '.$count->poster->name : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-6 text-center text-gray-500">{{ __('No counts yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $counts->links() }}
    </x-page>
</x-app-layout>
