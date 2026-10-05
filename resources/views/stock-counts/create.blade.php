<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('New stock count')" />
    </x-slot>

    <x-page>
        <form method="POST" action="{{ route('stock-counts.store') }}" class="card card-body max-w-xl space-y-5">
            @csrf
            <x-field name="site_id" :label="__('Site')" required>
                @if ($sites->count() === 1)
                    <input type="hidden" name="site_id" value="{{ $sites->first()->id }}">
                    <input class="form-input bg-gray-100" value="{{ $sites->first()->code }} · {{ $sites->first()->name }}" disabled>
                @else
                    <x-select name="site_id" :options="$sites->mapWithKeys(fn ($s) => [$s->id => $s->code.' · '.$s->name])->all()" :value="$siteId" :placeholder="__('— Choose —')" required />
                @endif
            </x-field>
            <x-field name="scope_note" :label="__('Scope')" :hint="__('e.g. Class A monthly, Truss Saw spares, shelf row C')">
                <x-input name="scope_note" maxlength="255" autofocus />
            </x-field>
            <div class="flex gap-2">
                <button class="btn-primary">{{ __('Create count') }}</button>
                <a href="{{ route('stock-counts.index') }}" class="btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </x-page>
</x-app-layout>
