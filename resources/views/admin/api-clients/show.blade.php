@php
    use App\Support\Format;
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$client->name" :subtitle="__('API client')">
            <a href="{{ route('admin.api-clients.edit', $client) }}" class="btn-secondary">{{ __('Edit') }}</a>
        </x-page-header>
    </x-slot>

    <x-page>
        @if ($token)
            <section class="card card-body space-y-3 border-2 border-amber-300 bg-amber-50" x-data="{ copied: false }">
                <h3 class="font-semibold text-gray-900">{{ __('Token: copy it now') }}</h3>
                <p class="text-sm text-gray-700">{{ __('It is shown only this once. Give it to the application’s developer through a safe channel, not by plain email. If it is lost, issue a new one.') }}</p>
                <div class="flex flex-wrap items-center gap-2">
                    <input readonly value="{{ $token }}" class="form-input flex-1 font-mono text-sm" aria-label="{{ __('Token') }}" x-ref="token" @focus="$el.select()">
                    <button type="button" class="btn-secondary" @click="navigator.clipboard.writeText($refs.token.value); copied = true">
                        <span x-show="! copied">{{ __('Copy') }}</span><span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                    </button>
                </div>
                <p class="text-xs text-gray-600">{{ __('The application sends it in every request as the header') }} <span class="font-mono">Authorization: Bearer …</span></p>
            </section>
        @endif

        <div class="card card-body">
            <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-[10rem_1fr]">
                <dt class="text-gray-500">{{ __('Data from') }}</dt><dd>{{ $client->site ? $client->site->code.' · '.$client->site->name : __('All sites') }}</dd>
                <dt class="text-gray-500">{{ __('Access') }}</dt><dd>{{ __('Read only') }}</dd>
                <dt class="text-gray-500">{{ __('Status') }}</dt><dd><x-active-badge :active="$client->is_active" /></dd>
                <dt class="text-gray-500">{{ __('Token') }}</dt><dd>{{ $hasToken ? __('Issued') : __('None') }}</dd>
                <dt class="text-gray-500">{{ __('Last used') }}</dt><dd>{{ $lastUsed ? Format::datetime($lastUsed) : __('never') }}</dd>
                <dt class="text-gray-500">{{ __('Created') }}</dt><dd>{{ Format::datetime($client->created_at) }} · {{ $client->creator->name }}</dd>
                <dt class="text-gray-500">{{ __('Notes') }}</dt><dd class="whitespace-pre-line">{{ $client->notes ?: '—' }}</dd>
            </dl>
        </div>

        <form method="POST" action="{{ route('admin.api-clients.token', $client) }}" class="card card-body flex flex-wrap items-center justify-between gap-3"
              x-data="{ sure: false }">
            @csrf
            <p class="max-w-xl text-sm text-gray-600">{{ __('Issue a new token when the old one is lost or may have been seen by someone else. The old token stops working at once.') }}</p>
            <div class="flex items-center gap-2">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" x-model="sure" class="form-checkbox"> {{ __('Replace the current token') }}</label>
                <button class="btn-primary" :disabled="! sure" :class="! sure && 'opacity-50 cursor-not-allowed'">{{ $hasToken ? __('Issue new token') : __('Issue token') }}</button>
            </div>
        </form>
    </x-page>
</x-app-layout>
