<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApiClientRequest;
use App\Models\ApiClient;
use App\Models\Site;
use App\Support\CsvExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applications allowed to read through the API, and their tokens (SPEC 7a). A token is shown
 * once, right after it is created; afterwards only a new one can be issued.
 */
class ApiClientController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ApiClient::class);

        $query = ApiClient::query()->with(['site', 'tokens'])->orderBy('name');

        if (CsvExport::requested($request)) {
            return CsvExport::download('api-clients', ['Name', 'Site', 'Active', 'Has token', 'Last used (UTC)', 'Created (UTC)'],
                $query->lazy()->map(fn (ApiClient $c) => [$c->name, $c->site?->code ?? 'All sites', $c->is_active,
                    $c->tokens->isNotEmpty(), $c->tokens->max('last_used_at')?->utc(), $c->created_at->utc()]));
        }

        return response()->view('admin.api-clients.index', ['clients' => $query->get()]);
    }

    public function create(): View
    {
        Gate::authorize('create', ApiClient::class);

        return view('admin.api-clients.form', ['client' => new ApiClient(['is_active' => true]), 'sites' => $this->sites()]);
    }

    public function store(ApiClientRequest $request): RedirectResponse
    {
        $client = new ApiClient($request->validated());
        $client->created_by = $request->user()->id;
        $client->save();

        return redirect()->route('admin.api-clients.show', $client)
            ->with('api_token', $client->issueToken())
            ->with('success', __('API client created. Copy the token now: it is shown only once.'));
    }

    public function show(ApiClient $apiClient): View
    {
        Gate::authorize('view', $apiClient);

        return view('admin.api-clients.show', [
            'client' => $apiClient->load(['site', 'creator']),
            'token' => session('api_token'),
            'hasToken' => $apiClient->tokens()->exists(),
            'lastUsed' => $apiClient->lastUsedAt(),
        ]);
    }

    public function edit(ApiClient $apiClient): View
    {
        Gate::authorize('update', $apiClient);

        return view('admin.api-clients.form', ['client' => $apiClient, 'sites' => $this->sites()]);
    }

    public function update(ApiClientRequest $request, ApiClient $apiClient): RedirectResponse
    {
        $apiClient->update([...$request->validated(), 'is_active' => $request->boolean('is_active')]);

        return redirect()->route('admin.api-clients.show', $apiClient)->with('success', __('API client saved.'));
    }

    /**
     * Issues a new token. The old one stops working at once.
     */
    public function token(ApiClient $apiClient): RedirectResponse
    {
        Gate::authorize('update', $apiClient);

        return redirect()->route('admin.api-clients.show', $apiClient)
            ->with('api_token', $apiClient->issueToken())
            ->with('success', __('New token issued; the previous token no longer works. Copy it now: it is shown only once.'));
    }

    /**
     * @return array<int, string>
     */
    private function sites(): array
    {
        return Site::query()->orderBy('code')->get()->mapWithKeys(fn (Site $s) => [$s->id => $s->code.' · '.$s->name])->all();
    }
}
