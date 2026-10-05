<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\Site;
use App\Models\User;
use App\Support\CsvExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $query = User::query()->with('site')->orderByDesc('is_active')->orderBy('name');

        if (CsvExport::requested($request)) {
            return CsvExport::download('users', ['Name', 'Email', 'Role', 'Site', 'Active', 'Daily digest', 'Last login (UTC)'],
                $query->lazy()->map(fn (User $u) => [$u->name, $u->email, $u->role, $u->site?->code, $u->is_active, $u->notify_low_stock, $u->last_login_at]));
        }

        return response()->view('admin.users.index', ['users' => $query->get()]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('admin.users.form', $this->formData(new User(['role' => Role::Manager, 'is_active' => true, 'notify_low_stock' => true])));
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = User::query()->create($request->attributesToSave());

        return redirect()->route('admin.users.index')->with('success', __('Account for :name created. Give them the password in person, or send a reset link.', ['name' => $user->name]));
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('admin.users.form', $this->formData($user));
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $user->update($request->attributesToSave());

        return redirect()->route('admin.users.index')->with('success', __('Account updated.'));
    }

    /**
     * Let the user choose a password themselves, by email.
     */
    public function sendResetLink(User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $status = Password::sendResetLink(['email' => $user->email]);

        return back()->with($status === Password::RESET_LINK_SENT ? 'success' : 'error', __($status));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(User $user): array
    {
        return [
            'user' => $user,
            'roles' => collect(Role::cases())->mapWithKeys(fn (Role $r) => [$r->value => $r->label()])->all(),
            'sites' => Site::query()->active()->orderBy('code')->get()->mapWithKeys(fn (Site $s) => [$s->id => $s->code.' · '.$s->name])->all(),
        ];
    }
}
