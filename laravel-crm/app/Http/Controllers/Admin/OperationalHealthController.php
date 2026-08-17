<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\OperationalHealthService;

class OperationalHealthController extends Controller
{
    public function __invoke(OperationalHealthService $health)
    {
        $this->authorize('ops.view');

        return view('admin.health.index', [
            'checks' => $health->check(),
            'checkedAt' => now(),
        ]);
    }
}
