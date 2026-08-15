<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;

class RolePermissionController extends Controller
{
    public function index()
    {
        $this->authorize('roles.view');

        return view('admin.roles.index', [
            'roles' => Role::with('permissions')->orderBy('name')->get(),
        ]);
    }
}
