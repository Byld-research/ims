<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Suppliers')" :subtitle="__('The Kraków warehouse is listed here like any other supplier.')">
            <x-export-link />
            @can('create', App\Models\Supplier::class)
                <a href="{{ route('suppliers.create') }}" class="btn-primary">{{ __('New supplier') }}</a>
            @endcan
        </x-page-header>
    </x-slot>

    <x-page>
        <form method="GET" class="card card-body flex flex-wrap items-end gap-3">
            <div class="grow">
                <label for="q" class="block text-xs font-medium text-gray-600">{{ __('Search') }}</label>
                <input type="search" name="q" id="q" value="{{ $filters['q'] ?? '' }}" class="form-input mt-1" placeholder="{{ __('Supplier name') }}">
            </div>
            <label class="inline-flex items-center gap-1.5 text-sm text-gray-700 pb-2">
                <input type="checkbox" name="inactive" value="1" @checked($filters['inactive'] ?? false) class="form-checkbox">
                {{ __('Include inactive') }}
            </label>
            <button class="btn-primary btn-sm">{{ __('Filter') }}</button>
        </form>

        <div class="card table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Contact') }}</th>
                        <th class="num">{{ __('Lead time') }}</th>
                        <th class="num">{{ __('Items') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($suppliers as $supplier)
                        <tr>
                            <td><a class="link" href="{{ route('suppliers.show', $supplier) }}">{{ $supplier->name }}</a></td>
                            <td class="text-gray-600">{{ collect([$supplier->contact_email, $supplier->contact_phone])->filter()->join(' · ') ?: '—' }}</td>
                            <td class="num">{{ $supplier->lead_time_days !== null ? $supplier->lead_time_days.' '.__('days') : '—' }}</td>
                            <td class="num">{{ $supplier->supplier_items_count }}</td>
                            <td><x-active-badge :active="$supplier->is_active" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-gray-500">{{ __('No suppliers yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $suppliers->links() }}
    </x-page>
</x-app-layout>
