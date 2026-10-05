<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
            <span class="text-gray-500 font-normal">· {{ $site?->name ?? __('All sites') }}</span>
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-gray-700">
                {{ __('Alerts and figures will appear here once stock is being recorded.') }}
            </div>
        </div>
    </div>
</x-app-layout>
