<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Categories')" :subtitle="__('Top-level groups are structural: items go into their subcategories.')">
            <x-export-link />
            @can('create', App\Models\Category::class)
                <a href="{{ route('categories.create') }}" class="btn-primary">{{ __('New category') }}</a>
            @endcan
        </x-page-header>
    </x-slot>

    <x-page>
        <div class="card table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Default location') }}</th>
                        <th class="num">{{ __('Items') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $top)
                        @foreach ([$top, ...$top->children] as $category)
                            <tr>
                                <td class="{{ $category->parent_id ? 'ps-8' : 'font-semibold' }}">
                                    <a class="link" href="{{ route('stock.index', ['category' => $category->id]) }}">{{ $category->name }}</a>
                                </td>
                                <td>
                                    @if ($category->is_structural)
                                        <span class="badge-gray">{{ __('Structural') }}</span>
                                    @endif
                                </td>
                                <td>{{ $category->default_bin }}</td>
                                <td class="num">{{ $category->items_count }}</td>
                                <td class="text-right whitespace-nowrap">
                                    @can('update', $category)
                                        <a class="link" href="{{ route('categories.edit', $category) }}">{{ __('Edit') }}</a>
                                        @unless ($category->parent_id)
                                            <span class="text-gray-300">·</span>
                                            <a class="link" href="{{ route('categories.create', ['parent' => $category->id]) }}">{{ __('Add subcategory') }}</a>
                                        @endunless
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="5" class="text-gray-500">{{ __('No categories yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-page>
</x-app-layout>
