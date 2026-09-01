<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::table('permissions')->updateOrInsert([
            'slug' => 'customers.delete',
        ], [
            'name' => 'Delete customers',
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        $permissionId = DB::table('permissions')->where('slug', 'customers.delete')->value('id');
        $roleIds = DB::table('roles')->whereIn('slug', ['super-admin', 'admin'])->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->updateOrInsert([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ], [
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        }
    }

    public function down()
    {
        $permissionId = DB::table('permissions')->where('slug', 'customers.delete')->value('id');

        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
