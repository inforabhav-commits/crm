<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\CrmMasterValue;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            'Super Admin',
            'Admin',
            'Manager',
            'Team Leader',
            'Agent',
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate([
                'slug' => str($roleName)->lower()->replace(' ', '-')->toString(),
            ], [
                'name' => $roleName,
            ]);
        }

        $permissions = [
            'users.view' => 'View users',
            'users.create' => 'Create users',
            'users.edit' => 'Edit users',
            'users.activate' => 'Activate or deactivate users',
            'roles.view' => 'View roles and permissions',
            'roles.manage' => 'Manage roles and permissions',
            'teams.view' => 'View teams',
            'teams.create' => 'Create teams',
            'teams.edit' => 'Edit teams',
            'teams.manage_members' => 'Manage team members',
            'crm_settings.view' => 'View CRM settings',
            'crm_settings.manage' => 'Manage CRM settings',
            'leads.view' => 'View leads',
            'leads.create' => 'Create leads',
            'leads.edit' => 'Edit leads',
            'leads.assign' => 'Assign leads',
            'leads.qualify' => 'Qualify leads',
            'leads.convert' => 'Convert leads',
            'activities.view' => 'View activities',
            'activities.create' => 'Create activities',
            'activities.edit' => 'Edit activities',
            'activities.complete' => 'Complete activities',
            'customers.view' => 'View customers',
            'customers.view_full_phone' => 'View full customer phone numbers',
            'customers.create' => 'Create customers',
            'customers.edit' => 'Edit customers',
            'contacts.view' => 'View contacts',
            'contacts.create' => 'Create contacts',
            'contacts.edit' => 'Edit contacts',
            'opportunities.view' => 'View opportunities',
            'opportunities.create' => 'Create opportunities',
            'opportunities.edit' => 'Edit opportunities',
            'opportunities.change_stage' => 'Change opportunity stage',
            'calls.view' => 'View call logs',
            'calls.initiate' => 'Initiate JustCall calls',
            'calls.update' => 'Update call disposition and notes',
            'calls.recordings.view' => 'View call recordings',
            'audit.view' => 'View audit logs',
            'reports.view' => 'View reports',
            'import.leads' => 'Import leads',
            'import.customers' => 'Import customers and contacts',
            'export.crm' => 'Export CRM data',
            'workflows.view' => 'View workflow rules',
            'workflows.manage' => 'Manage workflow rules',
            'ops.view' => 'View operational health',
            'justcall.view' => 'View JustCall settings',
            'justcall.manage' => 'Manage JustCall settings',
            'justcall.monitor' => 'Monitor JustCall integration health',
        ];

        foreach ($permissions as $slug => $name) {
            Permission::firstOrCreate(['slug' => $slug], ['name' => $name]);
        }

        $managementPermissions = Permission::whereIn('slug', array_keys($permissions))->pluck('id')->all();
        Role::whereIn('slug', ['super-admin', 'admin'])->get()->each(function (Role $role) use ($managementPermissions) {
            $role->permissions()->syncWithoutDetaching($managementPermissions);
        });

        $managerPermissions = Permission::whereIn('slug', ['users.view', 'roles.view', 'teams.view', 'reports.view', 'import.leads', 'import.customers', 'export.crm', 'workflows.view', 'workflows.manage', 'leads.view', 'leads.create', 'leads.edit', 'leads.assign', 'leads.qualify', 'leads.convert', 'activities.view', 'activities.create', 'activities.edit', 'activities.complete', 'customers.view', 'customers.create', 'customers.edit', 'contacts.view', 'contacts.create', 'contacts.edit', 'opportunities.view', 'opportunities.create', 'opportunities.edit', 'opportunities.change_stage', 'calls.view', 'calls.initiate', 'calls.update', 'calls.recordings.view'])->pluck('id')->all();
        Role::whereIn('slug', ['manager', 'team-leader'])->get()->each(function (Role $role) use ($managerPermissions) {
            $role->permissions()->syncWithoutDetaching($managerPermissions);
        });

        $agentPermissions = Permission::whereIn('slug', ['reports.view', 'export.crm', 'leads.view', 'leads.create', 'leads.edit', 'leads.qualify', 'leads.convert', 'activities.view', 'activities.create', 'activities.edit', 'activities.complete', 'customers.view', 'customers.create', 'customers.edit', 'contacts.view', 'contacts.create', 'contacts.edit', 'opportunities.view', 'opportunities.create', 'opportunities.edit', 'opportunities.change_stage', 'calls.view', 'calls.initiate', 'calls.update', 'calls.recordings.view'])->pluck('id')->all();
        Role::where('slug', 'agent')->get()->each(function (Role $role) use ($agentPermissions) {
            $role->permissions()->syncWithoutDetaching($agentPermissions);
        });

        $this->seedCrmMasterValues();

        if (env('ADMIN_EMAIL') && env('ADMIN_PASSWORD')) {
            $admin = User::firstOrCreate([
                'email' => env('ADMIN_EMAIL'),
            ], [
                'name' => env('ADMIN_NAME', 'CRM Administrator'),
                'password' => Hash::make(env('ADMIN_PASSWORD')),
                'is_active' => true,
            ]);

            $adminRole = Role::where('slug', 'super-admin')->first();
            if ($adminRole) {
                $admin->roles()->syncWithoutDetaching([$adminRole->id]);
            }
        }
    }

    private function seedCrmMasterValues(): void
    {
        $values = [
            'lead_status' => ['New', 'Contacted', 'Qualified', 'Converted', 'Lost'],
            'lead_source' => ['Website', 'Referral', 'Phone', 'Campaign'],
            'opportunity_stage' => ['Prospecting', 'Proposal', 'Negotiation', 'Won', 'Lost'],
            'activity_type' => ['Call', 'Email', 'Meeting', 'Follow-up', 'Visit'],
            'loss_reason' => ['Budget', 'No Response', 'Competitor', 'Not Fit'],
        ];

        foreach ($values as $type => $names) {
            foreach ($names as $index => $name) {
                CrmMasterValue::firstOrCreate([
                    'type' => $type,
                    'slug' => CrmMasterValue::makeSlug($name),
                ], [
                    'name' => $name,
                    'sort_order' => $index + 1,
                    'is_default' => $index === 0,
                    'is_active' => true,
                ]);
            }
        }
    }
}
