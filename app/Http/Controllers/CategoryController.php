<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use App\Support\CsvExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Category::class);

        if (CsvExport::requested($request)) {
            return CsvExport::download('categories', ['Group', 'Category', 'Structural', 'Default bin', 'Items'],
                Category::query()->with('parent')->withCount('items')->orderByRaw('COALESCE(parent_id, id), parent_id IS NOT NULL, name')->get()
                    ->map(fn (Category $c) => [$c->parent?->name ?? $c->name, $c->parent ? $c->name : '', $c->is_structural, $c->default_bin, $c->items_count]));
        }

        $categories = Category::query()
            ->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->withCount('items')->orderBy('name')])
            ->withCount('items')
            ->orderBy('name')
            ->get();

        return response()->view('categories.index', compact('categories'));
    }

    public function create(): View
    {
        Gate::authorize('create', Category::class);

        return view('categories.form', [
            'category' => new Category(['parent_id' => request()->integer('parent') ?: null]),
            'parents' => $this->parentOptions(),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        Category::query()->create($request->validated());

        return redirect()->route('categories.index')->with('success', __('Category created.'));
    }

    public function edit(Category $category): View
    {
        Gate::authorize('update', $category);

        return view('categories.form', [
            'category' => $category,
            'parents' => $this->parentOptions($category),
        ]);
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()->route('categories.index')->with('success', __('Category updated.'));
    }

    /**
     * @return array<int, string>
     */
    private function parentOptions(?Category $except = null): array
    {
        return Category::query()
            ->whereNull('parent_id')
            ->when($except, fn ($q) => $q->whereKeyNot($except->id))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
