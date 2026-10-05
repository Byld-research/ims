<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\CsvExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Master data changes (SPEC 4.16). Stock movements are in the ledger, not here.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('view-audit-log');

        $filters = $request->validate([
            'entity' => ['nullable', 'string', 'max:40'],
            'entity_id' => ['nullable', 'integer'],
            'user' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = AuditLog::query()
            ->with('user')
            ->when($filters['entity'] ?? null, fn ($q, $entity) => $q->where('entity', $entity))
            ->when($filters['entity_id'] ?? null, fn ($q, $id) => $q->where('entity_id', $id))
            ->when($filters['user'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<', now()->parse($to)->addDay()))
            ->latest('id');

        if (CsvExport::requested($request)) {
            return CsvExport::download('audit-log', ['When (UTC)', 'User', 'Entity', 'Id', 'Action', 'Changes'],
                $query->lazy(500)->map(fn (AuditLog $log) => [$log->created_at->utc(), $log->user?->email ?? 'system', $log->entity,
                    $log->entity_id, $log->action, json_encode($log->changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]));
        }

        return response()->view('admin.audit.index', [
            'logs' => $query->paginate(50)->withQueryString(),
            'filters' => $filters,
            'entities' => AuditLog::query()->distinct()->orderBy('entity')->pluck('entity')->all(),
            'users' => User::query()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }
}
