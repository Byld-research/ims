<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Machine types')" :subtitle="__('Each type holds one parts list, shared by every identical machine at both sites.')">
            <x-export-link />
            @can('create', App\Models\MachineType::class)
                <a href="{{ route('machine-types.create') }}" class="btn-primary">{{ __('New machine type') }}</a>
            @endcan
        </x-page-header>
    </x-slot>

    <x-page>
        <div class="card table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Code') }}</th>
                        <th>{{ __('Name') }}</th>
                        <th class="num">{{ __('Parts') }}</th>
                        <th class="num">{{ __('Work centres') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($machineTypes as $machineType)
                        <tr>
                            <td class="font-mono"><a class="link" href="{{ route('machine-types.show', $machineType) }}">{{ $machineType->code }}</a></td>
                            <td>{{ $machineType->name }}</td>
                            <td class="num">{{ $machineType->parts_list_count }}</td>
                            <td class="num">{{ $machineType->work_centers_count }}</td>
                            <td><x-active-badge :active="$machineType->is_active" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-gray-500">{{ __('No machine types yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-page>
</x-app-layout>
