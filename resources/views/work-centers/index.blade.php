<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Work centres')" :subtitle="$site ? $site->code.' · '.$site->name : __('All sites')">
            <x-export-link />
            @can('create', App\Models\WorkCenter::class)
                <a href="{{ route('work-centers.create') }}" class="btn-primary">{{ __('New work centre') }}</a>
            @endcan
        </x-page-header>
    </x-slot>

    <x-page>
        <form method="GET" class="flex justify-end">
            <label class="inline-flex items-center gap-1.5 text-sm text-gray-700">
                <input type="checkbox" name="inactive" value="1" @checked($filters['inactive'] ?? false) class="form-checkbox" onchange="this.form.submit()">
                {{ __('Include inactive') }}
            </label>
        </form>

        <div class="card table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        @unless ($site)<th>{{ __('Site') }}</th>@endunless
                        <th>{{ __('Code') }}</th>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Machine type') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($workCenters as $workCenter)
                        <tr>
                            @unless ($site)<td>{{ $workCenter->site->code }}</td>@endunless
                            <td class="font-mono"><a class="link" href="{{ route('work-centers.show', $workCenter) }}">{{ $workCenter->code }}</a></td>
                            <td>{{ $workCenter->name }}</td>
                            <td>{{ $workCenter->machineType?->name ?? '—' }}</td>
                            <td><x-active-badge :active="$workCenter->is_active" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-gray-500">{{ __('No work centres yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-page>
</x-app-layout>
