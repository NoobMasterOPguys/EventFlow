<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $logs = AuditLog::query()
            ->with('actor:id,name,email')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('action', 'like', '%'.$search.'%')
                        ->orWhere('auditable_type', 'like', '%'.$search.'%')
                        ->orWhere('ip_address', 'like', '%'.$search.'%')
                        ->orWhereHas('actor', function (Builder $actorQuery) use ($search): void {
                            $actorQuery->where('name', 'like', '%'.$search.'%')
                                ->orWhere('email', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when($filters['action'] ?? null, fn (Builder $query, string $action): Builder => $query->where('action', $action))
            ->when($filters['start_date'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
            ->when($filters['end_date'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.audit.index', [
            'logs' => $logs,
            'filters' => $filters,
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
