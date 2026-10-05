<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('New purchase order')" :subtitle="__('One order serves one site. Check the other site’s stock first: a transfer is usually faster.')" />
    </x-slot>

    <x-page>
        <form method="POST" action="{{ route('purchase-orders.store') }}" class="card card-body max-w-2xl space-y-5">
            @csrf

            <x-field name="supplier_id" :label="__('Supplier')" required>
                <x-select name="supplier_id" :options="$suppliers" :value="$supplierId" :placeholder="__('— Choose —')" required autofocus />
            </x-field>

            <x-field name="site_id" :label="__('Deliver to')" required>
                @if ($sites->count() === 1)
                    <input type="hidden" name="site_id" value="{{ $sites->first()->id }}">
                    <input class="form-input bg-gray-100" value="{{ $sites->first()->code }} · {{ $sites->first()->name }}" disabled>
                @else
                    <x-select name="site_id" :options="$sites->mapWithKeys(fn ($s) => [$s->id => $s->code.' · '.$s->name])->all()" :value="$siteId" :placeholder="__('— Choose —')" required />
                @endif
            </x-field>

            <x-field name="notes" :label="__('Notes')">
                <x-textarea name="notes" rows="3" />
            </x-field>

            <div class="flex gap-2">
                <button class="btn-primary">{{ __('Create draft') }}</button>
                <a href="{{ route('purchase-orders.index') }}" class="btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </x-page>
</x-app-layout>
