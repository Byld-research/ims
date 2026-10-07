<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

/**
 * An application allowed to read the system through the API (SPEC 7a). Read-only in v1:
 * its token carries the single ability "read". A site limits every answer to that site.
 */
#[Fillable(['name', 'site_id', 'notes', 'is_active'])]
class ApiClient extends Model implements AuthenticatableContract
{
    use Auditable, Authenticatable, HasApiTokens, HasFactory;

    /** Abilities a token is issued with. v1 is read-only. */
    public const ABILITIES = ['read'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * A new token; any earlier token stops working at once. The plain text is shown once.
     */
    public function issueToken(): string
    {
        return DB::transaction(function () {
            $this->tokens()->delete();

            return $this->createToken('api', self::ABILITIES)->plainTextToken;
        });
    }

    public function lastUsedAt(): ?Carbon
    {
        $value = $this->tokens()->max('last_used_at');

        return $value ? Carbon::parse($value) : null;
    }

    /**
     * Restricts a query on a site_id column to this client's site, when it has one.
     */
    public function limitToSite(Builder $query, string $column = 'site_id'): Builder
    {
        return $query->when($this->site_id, fn ($q, $siteId) => $q->where($column, $siteId));
    }
}
