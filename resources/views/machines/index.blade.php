<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Machines')" :subtitle="$site ? $site->code.' · '.$site->name : __('All sites')">
            <x-export-link />
            @can('create', App\Models\Machine::class)
                <a href="{{ route('machines.create') }}" class="btn-primary">{{ __('Register machine') }}</a>
            @endcan
        </x-page-header>
    </x-slot>

    <x-page>
        <form method="GET" class="card card-body flex flex-wrap items-end gap-3">
            <div class="w-64">
                <label for="type" class="block text-xs font-medium text-gray-600">{{ __('Type') }}</label>
                <select name="type" id="type" class="form-input mt-1" onchange="this.form.submit()">
                    <option value="">{{ __('All types') }}</option>
                    @foreach ($types as $id => $label)
                        <option value="{{ $id }}" @selected(($filters['type'] ?? null) == $id)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <label class="inline-flex items-center gap-1.5 text-sm text-gray-700 pb-2">
                <input type="checkbox" name="inactive" value="1" @checked($filters['inactive'] ?? false) class="form-checkbox" onchange="this.form.submit()">
                {{ __('Include inactive') }}
            </label>
        </form>

        <div class="card table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('SKU') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Rev.') }}</th>
                        @unless ($site)<th>{{ __('Site') }}</th>@endunless
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($machines as $machine)
                        <tr>
                            <td class="font-mono"><a class="link" href="{{ route('machines.show', $machine) }}">{{ $machine->sku }}</a></td>
                            <td><span class="badge-indigo font-mono">{{ $machine->machineType->code }}</span> {{ $machine->machineType->name }}</td>
                            <td>{{ $machine->name }}</td>
                            <td>{{ $machine->revision }}</td>
                            @unless ($site)<td>{{ $machine->site->code }}</td>@endunless
                            <td><x-active-badge :active="$machine->is_active" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-gray-500">{{ __('No machines registered here.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-page>
</x-app-layout>
