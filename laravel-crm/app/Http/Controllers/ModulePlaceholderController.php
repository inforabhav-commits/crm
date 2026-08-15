<?php

namespace App\Http\Controllers;

class ModulePlaceholderController extends Controller
{
    public function __invoke(string $module)
    {
        $modules = [
            'leads' => 'Leads',
            'customers' => 'Customers',
            'opportunities' => 'Opportunities',
            'activities' => 'Activities',
            'calls' => 'Calls',
            'reports' => 'Reports',
            'users' => 'Users',
            'roles' => 'Roles & Permissions',
            'teams' => 'Teams',
            'crm-settings' => 'CRM Settings',
            'justcall-settings' => 'JustCall Settings',
        ];

        abort_unless(isset($modules[$module]), 404);

        return view('modules.placeholder', [
            'title' => $modules[$module],
        ]);
    }
}
