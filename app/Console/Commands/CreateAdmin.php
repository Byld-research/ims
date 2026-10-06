<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Creates the first administrator, or resets an administrator's password. The password is
 * asked for without echo, so it never appears in shell history or logs.
 */
#[Signature('ims:create-admin {email} {--name=Administrator}')]
#[Description('Create an administrator account (or reset its password), asking for the password without echo')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));

        if (Validator::make(['email' => $email], ['email' => 'required|email'])->fails()) {
            $this->error('That is not a valid email address.');

            return self::FAILURE;
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing && ! $existing->isAdmin()) {
            $this->error("{$email} exists and is not an administrator. Change its role under Admin → Users instead.");

            return self::FAILURE;
        }

        $password = (string) $this->secret('Password (not shown)');
        $confirmation = (string) $this->secret('Repeat the password');

        $validator = Validator::make(
            ['password' => $password, 'password_confirmation' => $confirmation],
            ['password' => ['required', 'confirmed', Password::defaults()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = $existing ?? new User(['email' => $email, 'role' => Role::Admin, 'site_id' => null, 'notify_low_stock' => true]);
        $user->fill(['name' => $existing?->name ?? $this->option('name'), 'password' => $password, 'is_active' => true])->save();

        $this->info($existing ? "Password reset for administrator {$email}." : "Administrator {$email} created. Log in and add the other users under Admin → Users.");

        return self::SUCCESS;
    }
}
