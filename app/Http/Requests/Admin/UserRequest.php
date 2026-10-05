<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

/**
 * Accounts are created and maintained by administrators only (SPEC 6).
 */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user ? $this->user()->can('update', $user) : $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user)],
            'role' => ['required', Rule::enum(Role::class)],
            // Administrators work across sites; everyone else belongs to one (SPEC 4.15).
            'site_id' => [Rule::requiredIf($this->input('role') !== Role::Admin->value), 'nullable', 'integer',
                Rule::exists('sites', 'id')->where('is_active', true)],
            'is_active' => ['boolean'],
            'notify_low_stock' => ['boolean'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::defaults()],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            /** @var User|null $user */
            $user = $this->route('user');

            if (! $user || ! $user->isAdmin()) {
                return;
            }

            $losesAdmin = $this->input('role') !== Role::Admin->value || ! $this->boolean('is_active');

            if ($losesAdmin && $user->is($this->user())) {
                $validator->errors()->add('role', __('You cannot remove your own administrator access or deactivate yourself.'));
            } elseif ($losesAdmin && User::query()->active()->where('role', Role::Admin)->whereKeyNot($user->id)->doesntExist()) {
                $validator->errors()->add('role', __('This is the last active administrator.'));
            }
        }];
    }

    /**
     * @return array<string, mixed>
     */
    public function attributesToSave(): array
    {
        $data = $this->safe()->except(['password', 'password_confirmation']);

        if ($data['role'] === Role::Admin->value) {
            $data['site_id'] = null;
        }

        if (filled($this->validated('password'))) {
            $data['password'] = $this->validated('password');
        }

        return $data;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
            'notify_low_stock' => $this->boolean('notify_low_stock'),
        ]);
    }
}
