<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Machine types')" :subtitle="__('Each type holds one parts list, shared by every machine of that type at both sites.')">
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
                        <th>{{ __('Letter') }}</th>
                        <th>{{ __('Name') }}</th>
                        <th class="num">{{ __('Last serial') }}</th>
                        <th class="num">{{ __('Parts') }}</th>
                        <th class="num">{{ __('Machines') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($machineTypes as $machineType)
                        <tr>
                            <td><a class="badge-indigo font-mono" href="{{ route('machine-types.show', $machineType) }}">{{ $machineType->code }}</a></td>
                            <td><a class="link" href="{{ route('machine-types.show', $machineType) }}">{{ $machineType->name }}</a></td>
                            <td class="num font-mono">{{ sprintf('%03d', $machineType->last_serial) }}</td>
                            <td class="num">{{ $machineType->parts_list_count }}</td>
                            <td class="num">{{ $machineType->machines_count }}</td>
                            <td><x-active-badge :active="$machineType->is_active" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-gray-500">{{ __('No machine types yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-page>
</x-app-layout>
