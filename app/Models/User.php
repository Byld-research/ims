<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'site_id', 'is_active', 'notify_low_stock'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
            'notify_low_stock' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isManager(): bool
    {
        return $this->role === Role::Manager;
    }

    public function isOperator(): bool
    {
        return $this->role === Role::Operator;
    }

    /**
     * Whether the user may record changes for the given site (SPEC 6).
     * Administrators write everywhere; managers only at their own site; operators never.
     */
    public function canWriteSite(Site|int|null $site): bool
    {
        if (! $this->is_active || $site === null) {
            return false;
        }

        $siteId = $site instanceof Site ? $site->id : $site;

        return match ($this->role) {
            Role::Admin => true,
            Role::Manager => $this->site_id !== null && $this->site_id === $siteId,
            Role::Operator => false,
        };
    }

    /**
     * Admins and managers may maintain shared master data (items, suppliers, machine types).
     */
    public function canEditMasterData(): bool
    {
        return $this->is_active && in_array($this->role, [Role::Admin, Role::Manager], true);
    }
}
