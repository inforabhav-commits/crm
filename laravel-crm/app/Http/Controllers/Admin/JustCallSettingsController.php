<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\Integrations\JustCall\JustCallClient;
use Illuminate\Http\Request;

class JustCallSettingsController extends Controller
{
    public function index(JustCallClient $justCall)
    {
        $this->authorize('justcall.view');

        return view('admin.justcall-settings.index', [
            'status' => $justCall->status(),
            'lastTest' => session('justcall_test'),
        ]);
    }

    public function test(Request $request, JustCallClient $justCall, AuditService $audit)
    {
        $this->authorize('justcall.manage');

        $result = $justCall->testConnection();
        $audit->log('justcall.connection_tested', null, 'JustCall connection test executed.', null, [
            'ok' => $result['ok'],
            'status' => $result['status'],
            'message' => $result['message'],
        ], $request->user(), $request);

        return back()
            ->with('justcall_test', $result)
            ->with($result['ok'] ? 'status' : 'error', $result['message']);
    }
}
