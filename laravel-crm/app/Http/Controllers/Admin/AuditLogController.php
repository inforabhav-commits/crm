<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('audit.view');

        $filters = $request->only(['user', 'action', 'entity_type', 'date']);
        $query = AuditLog::with('user')->latest();

        if (! empty($filters['user'])) {
            $query->where('user_id', $filters['user']);
        }

        if ($action = trim((string) ($filters['action'] ?? ''))) {
            $query->where('action', 'like', "%{$action}%");
        }

        if ($entityType = trim((string) ($filters['entity_type'] ?? ''))) {
            $query->where('entity_type', $entityType);
        }

        if (! empty($filters['date'])) {
            $query->whereDate('created_at', $filters['date']);
        }

        return view('admin.audit-logs.index', [
            'logs' => $query->paginate(20)->withQueryString(),
            'users' => User::orderBy('name')->get(),
            'actions' => AuditLog::select('action')->distinct()->orderBy('action')->pluck('action'),
            'entityTypes' => AuditLog::select('entity_type')->whereNotNull('entity_type')->distinct()->orderBy('entity_type')->pluck('entity_type'),
            'filters' => $filters,
        ]);
    }
}
