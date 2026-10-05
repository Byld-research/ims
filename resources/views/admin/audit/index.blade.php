@php use App\Support\Format; @endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Audit log')" :subtitle="__('Changes to master data. Stock movements are in each item’s movement history.')">
            <x-export-link />
        </x-page-header>
    </x-slot>

    <x-page>
        <form method="GET" class="card card-body grid gap-3 sm:grid-cols-6 items-end">
            <div>
                <label for="entity" class="block text-xs font-medium text-gray-600">{{ __('Record type') }}</label>
                <x-select name="entity" :options="array_combine($entities, $entities)" :value="$filters['entity'] ?? null" :placeholder="__('Any')" class="mt-1" />
            </div>
            <div>
                <label for="entity_id" class="block text-xs font-medium text-gray-600">{{ __('Record id') }}</label>
                <input type="number" name="entity_id" id="entity_id" value="{{ $filters['entity_id'] ?? '' }}" class="form-input mt-1">
            </div>
            <div>
                <label for="user" class="block text-xs font-medium text-gray-600">{{ __('Changed by') }}</label>
                <x-select name="user" :options="$users" :value="$filters['user'] ?? null" :placeholder="__('Anyone')" class="mt-1" />
            </div>
            <div>
                <label for="from" class="block text-xs font-medium text-gray-600">{{ __('From') }}</label>
                <input type="date" name="from" id="from" value="{{ $filters['from'] ?? '' }}" class="form-input mt-1">
            </div>
            <div>
                <label for="to" class="block text-xs font-medium text-gray-600">{{ __('To') }}</label>
                <input type="date" name="to" id="to" value="{{ $filters['to'] ?? '' }}" class="form-input mt-1">
            </div>
            <button class="btn-primary btn-sm">{{ __('Filter') }}</button>
        </form>

        <div class="card table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('When') }}</th>
                        <th>{{ __('By') }}</th>
                        <th>{{ __('Record') }}</th>
                        <th>{{ __('Action') }}</th>
                        <th>{{ __('Changes') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="whitespace-nowrap text-gray-600">{{ Format::datetime($log->created_at) }}</td>
                            <td class="whitespace-nowrap">{{ $log->user?->name ?? __('system') }}</td>
                            <td class="whitespace-nowrap font-mono">{{ $log->entity }} #{{ $log->entity_id }}</td>
                            <td><span class="badge-gray">{{ strtolower($log->action->value) }}</span></td>
                            <td class="text-xs">
                                @foreach ($log->changes as $field => $change)
                                    <div>
                                        <span class="font-medium text-gray-700">{{ $field }}</span>:
                                        @if ($log->action === App\Enums\AuditAction::Update)
                                            <span class="text-gray-500 line-through">{{ json_encode($change['before'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</span>
                                            → <span class="text-gray-900">{{ json_encode($change['after'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</span>
                                        @else
                                            <span class="text-gray-900">{{ json_encode($change['after'] ?? $change['before'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</span>
                                        @endif
                                    </div>
                                @endforeach
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-gray-500">{{ __('No changes recorded.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $logs->links() }}
    </x-page>
</x-app-layout>
