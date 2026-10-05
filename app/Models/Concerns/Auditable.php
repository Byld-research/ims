<?php

namespace App\Models\Concerns;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Records master data changes in audit_logs: changed fields only, before and after (SPEC 4.16).
 *
 * Stock movements are not audited here; the ledger records them. Models list the attributes
 * that belong to the ledger or carry secrets in $auditExclude.
 */
trait Auditable
{
    /** Always left out of the audit trail. */
    private static array $auditAlwaysExcluded = ['created_at', 'updated_at', 'password', 'remember_token'];

    public static function bootAuditable(): void
    {
        static::created(fn ($model) => $model->writeAudit(AuditAction::Create, [], $model->auditableAttributes($model->getAttributes())));

        static::updated(function ($model) {
            $after = $model->auditableAttributes($model->getChanges());

            if ($after === []) {
                return;
            }

            $before = array_intersect_key($model->auditableAttributes($model->getPrevious()), $after);
            $model->writeAudit(AuditAction::Update, $before, $after);
        });

        static::deleted(fn ($model) => $model->writeAudit(AuditAction::Delete, $model->auditableAttributes($model->getAttributes()), []));
    }

    /**
     * The short entity name stored in audit_logs.entity, e.g. "item", "machine_type".
     */
    public function auditEntity(): string
    {
        return Str::snake(class_basename($this));
    }

    private function auditableAttributes(array $attributes): array
    {
        $excluded = [...self::$auditAlwaysExcluded, ...($this->auditExclude ?? [])];

        return array_map(
            fn ($value) => $value instanceof \BackedEnum ? $value->value : $value,
            array_diff_key($attributes, array_flip($excluded)),
        );
    }

    private function writeAudit(AuditAction $action, array $before, array $after): void
    {
        $changes = [];

        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $field) {
            $changes[$field] = ['before' => $before[$field] ?? null, 'after' => $after[$field] ?? null];
        }

        AuditLog::query()->create([
            'entity' => $this->auditEntity(),
            'entity_id' => $this->getKey(),
            'action' => $action,
            'changes' => $changes,
            'user_id' => Auth::id(),
        ]);
    }
}
