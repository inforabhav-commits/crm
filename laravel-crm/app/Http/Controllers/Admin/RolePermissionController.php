<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;

class RolePermissionController extends Controller
{
    public function index()
    {
        $this->authorize('roles.view');

        return view('admin.roles.index', [
            'roles' => Role::with(['permissions' => fn ($query) => $query->orderBy('slug')])->orderBy('name')->get(),
            'permissionModules' => Permission::orderBy('slug')->get()->groupBy(fn (Permission $permission) => $this->moduleLabel($permission->slug)),
        ]);
    }

    private function moduleLabel(string $slug): string
    {
        $prefix = str($slug)->before('.')->toString();

        return match ($prefix) {
            'users' => 'Users',
            'roles' => 'Roles & Permissions',
            'teams' => 'Teams',
            'crm_settings' => 'CRM Settings',
            'leads' => 'Leads',
            'activities' => 'Activities',
            'customers' => 'Customers',
            'contacts' => 'Contacts',
            'opportunities' => 'Opportunities',
            'calls' => 'Calls',
            'audit' => 'Audit',
            'reports' => 'Reports',
            'import', 'export' => 'Import / Export',
            'workflows' => 'Workflow Rules',
            'ops' => 'Operations',
            'justcall' => 'JustCall',
            default => str($prefix)->replace('_', ' ')->title()->toString(),
        };
    }
}
