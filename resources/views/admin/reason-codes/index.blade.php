<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Reason codes')" :subtitle="__('Why stock was adjusted, or what a general issue was for.')">
            <x-export-link />
            <a href="{{ route('admin.reason-codes.create') }}" class="btn-primary">{{ __('New reason code') }}</a>
        </x-page-header>
    </x-slot>

    <x-page>
        <div class="card table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Used for') }}</th>
                        <th>{{ __('Code') }}</th>
                        <th>{{ __('Label') }}</th>
                        <th class="num">{{ __('Movements') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($codes as $code)
                        <tr>
                            <td>{{ $code->applies_to === App\Enums\ReasonCodeScope::Adjustment ? __('Adjustment') : __('General issue') }}</td>
                            <td class="font-mono">
                                <a class="link" href="{{ route('admin.reason-codes.edit', $code) }}">{{ $code->code }}</a>
                                @if ($code->applies_to === App\Enums\ReasonCodeScope::Adjustment && in_array($code->code, $system, true))
                                    <span class="badge-gray ms-1" title="{{ __('Used by the application itself') }}">{{ __('system') }}</span>
                                @endif
                            </td>
                            <td>{{ $code->label }}</td>
                            <td class="num">{{ $code->transactions_count }}</td>
                            <td><x-active-badge :active="$code->is_active" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-page>
</x-app-layout>
