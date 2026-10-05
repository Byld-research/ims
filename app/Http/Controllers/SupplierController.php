<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierRequest;
use App\Models\Supplier;
use App\Support\CsvExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Supplier::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'inactive' => ['nullable', 'boolean'],
        ]);

        $query = Supplier::query()
            ->withCount('supplierItems')
            ->unless($filters['inactive'] ?? false, fn ($q) => $q->active())
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where('name', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->orderBy('name');

        if (CsvExport::requested($request)) {
            return CsvExport::download('suppliers',
                ['Name', 'Email', 'Phone', 'Lead time (days)', 'Items', 'Active', 'Notes'],
                $query->lazy()->map(fn (Supplier $s) => [$s->name, $s->contact_email, $s->contact_phone,
                    $s->lead_time_days, $s->supplier_items_count, $s->is_active, $s->notes]));
        }

        return response()->view('suppliers.index', [
            'suppliers' => $query->paginate(50)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function show(Supplier $supplier): View
    {
        Gate::authorize('view', $supplier);

        $supplier->load(['supplierItems' => fn ($q) => $q->with('item')->join('items', 'items.id', '=', 'supplier_items.item_id')
            ->orderBy('items.sku')->select('supplier_items.*')]);

        return view('suppliers.show', compact('supplier'));
    }

    public function create(): View
    {
        Gate::authorize('create', Supplier::class);

        return view('suppliers.form', ['supplier' => new Supplier(['is_active' => true])]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::query()->create($request->validated());

        return redirect()->route('suppliers.show', $supplier)->with('success', __('Supplier created.'));
    }

    public function edit(Supplier $supplier): View
    {
        Gate::authorize('update', $supplier);

        return view('suppliers.form', compact('supplier'));
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return redirect()->route('suppliers.show', $supplier)->with('success', __('Supplier updated.'));
    }
}
