<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Min levels and bins')" />
    </x-slot>

    <x-page>
        <div class="card card-body max-w-xl space-y-3">
            <p class="text-sm text-gray-700">{{ __('Levels are set per site. Choose one:') }}</p>
            <div class="flex flex-wrap gap-2">
                @foreach ($sites as $site)
                    <a class="btn-secondary" href="{{ route('stock.levels', ['site' => $site->id]) }}">{{ $site->code }} · {{ $site->name }}</a>
                @endforeach
            </div>
        </div>
    </x-page>
</x-app-layout>
