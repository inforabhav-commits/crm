<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        foreach ([
            'users.view',
            'users.create',
            'users.edit',
            'users.activate',
            'roles.view',
            'roles.manage',
            'teams.view',
            'teams.create',
            'teams.edit',
            'teams.manage_members',
            'crm_settings.view',
            'crm_settings.manage',
            'leads.view',
            'leads.create',
            'leads.edit',
            'leads.assign',
            'leads.qualify',
            'leads.convert',
            'activities.view',
            'activities.create',
            'activities.edit',
            'activities.complete',
            'customers.view',
            'customers.create',
            'customers.edit',
            'contacts.view',
            'contacts.create',
            'contacts.edit',
            'opportunities.view',
            'opportunities.create',
            'opportunities.edit',
            'opportunities.change_stage',
            'calls.view',
            'calls.initiate',
            'calls.update',
            'calls.recordings.view',
            'audit.view',
            'reports.view',
            'import.leads',
            'import.customers',
            'export.crm',
            'workflows.view',
            'workflows.manage',
            'ops.view',
            'justcall.view',
            'justcall.manage',
            'justcall.monitor',
        ] as $permission) {
            Gate::define($permission, function ($user) use ($permission) {
                return $user->hasPermission($permission);
            });
        }
    }
}
