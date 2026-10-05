<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReasonCodeScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReasonCodeRequest;
use App\Models\ReasonCode;
use App\Support\CsvExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReasonCodeController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ReasonCode::class);

        $query = ReasonCode::query()->withCount('transactions')->orderBy('applies_to')->orderBy('code');

        if (CsvExport::requested($request)) {
            return CsvExport::download('reason-codes', ['Used for', 'Code', 'Label', 'Active', 'Movements'],
                $query->lazy()->map(fn (ReasonCode $c) => [$c->applies_to, $c->code, $c->label, $c->is_active, $c->transactions_count]));
        }

        return response()->view('admin.reason-codes.index', ['codes' => $query->get(), 'system' => ReasonCodeRequest::SYSTEM]);
    }

    public function create(): View
    {
        Gate::authorize('create', ReasonCode::class);

        return view('admin.reason-codes.form', $this->formData(new ReasonCode(['applies_to' => ReasonCodeScope::Adjustment, 'is_active' => true])));
    }

    public function store(ReasonCodeRequest $request): RedirectResponse
    {
        ReasonCode::query()->create($request->validated());

        return redirect()->route('admin.reason-codes.index')->with('success', __('Reason code created.'));
    }

    public function edit(ReasonCode $reasonCode): View
    {
        Gate::authorize('update', $reasonCode);

        return view('admin.reason-codes.form', $this->formData($reasonCode));
    }

    public function update(ReasonCodeRequest $request, ReasonCode $reasonCode): RedirectResponse
    {
        $reasonCode->update($request->validated());

        return redirect()->route('admin.reason-codes.index')->with('success', __('Reason code updated.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(ReasonCode $code): array
    {
        return [
            'code' => $code,
            'isSystem' => $code->exists && $code->applies_to === ReasonCodeScope::Adjustment && in_array($code->code, ReasonCodeRequest::SYSTEM, true),
            'scopes' => [ReasonCodeScope::Adjustment->value => __('Adjustment'), ReasonCodeScope::IssueGeneral->value => __('General issue')],
        ];
    }
}
