<div {{ $attributes->merge(['class' => 'py-8']) }}>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <x-flash />
        {{ $slot }}
    </div>
</div>
