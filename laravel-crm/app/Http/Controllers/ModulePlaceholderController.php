<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ModulePlaceholderController extends Controller
{
    public function __invoke(Request $request, string $module)
    {
        $modules = [
            'leads' => ['title' => 'Leads', 'permission' => 'leads.view'],
            'customers' => ['title' => 'Customers', 'permission' => 'customers.view'],
            'opportunities' => ['title' => 'Opportunities', 'permission' => 'opportunities.view'],
            'activities' => ['title' => 'Activities', 'permission' => 'activities.view'],
            'calls' => ['title' => 'Calls', 'permission' => 'calls.view'],
            'reports' => ['title' => 'Reports', 'permission' => 'reports.view'],
            'users' => ['title' => 'Users', 'permission' => 'users.view'],
            'roles' => ['title' => 'Roles & Permissions', 'permission' => 'roles.view'],
            'teams' => ['title' => 'Teams', 'permission' => 'teams.view'],
            'crm-settings' => ['title' => 'CRM Settings', 'permission' => 'crm_settings.view'],
            'justcall-settings' => ['title' => 'JustCall Settings', 'permission' => 'justcall.view'],
        ];

        abort_unless(isset($modules[$module]), 404);
        abort_unless($request->user()->can($modules[$module]['permission']), 403);

        return view('modules.placeholder', [
            'title' => $modules[$module]['title'],
        ]);
    }
}
