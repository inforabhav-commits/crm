@extends('layouts.crm', ['title' => 'JustCall Settings'])

@section('content')
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h2 class="h5 mb-0">JustCall Integration</h2>
                <div class="d-flex gap-2">
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.justcall-mappings.index') }}">User Mapping</a>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.justcall-webhooks.index') }}">Webhook Inbox</a>
                    @can('justcall.manage')
                        <form method="post" action="{{ route('admin.justcall-settings.test') }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-primary" type="submit">Test Connection</button>
                        </form>
                    @endcan
                </div>
            </div>

            <div class="row g-3">
                @foreach ([
                    'Enabled' => $status['enabled'],
                    'API Key Configured' => $status['api_key_configured'],
                    'API Secret Configured' => $status['api_secret_configured'],
                    'Webhook Secret Configured' => $status['webhook_secret_configured'],
                    'Credentials Ready' => $status['credentials_configured'],
                ] as $label => $value)
                    <div class="col-md-4">
                        <div class="border rounded-1 p-3 h-100">
                            <div class="text-muted small">{{ $label }}</div>
                            <div class="fw-semibold">{{ $value ? 'Yes' : 'No' }}</div>
                        </div>
                    </div>
                @endforeach
                <div class="col-md-4">
                    <div class="border rounded-1 p-3 h-100">
                        <div class="text-muted small">Base URL</div>
                        <div class="fw-semibold">{{ $status['base_url'] }}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded-1 p-3 h-100">
                        <div class="text-muted small">Auth Mode</div>
                        <div class="fw-semibold">{{ $status['auth_mode'] }}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded-1 p-3 h-100">
                        <div class="text-muted small">Secret Values</div>
                        <div class="fw-semibold">Masked server-side</div>
                    </div>
                </div>
            </div>

            @if ($lastTest)
                <div class="alert {{ $lastTest['ok'] ? 'alert-success' : 'alert-warning' }} mt-3 mb-0">
                    {{ $lastTest['message'] }}
                </div>
            @endif
        </div>
    </section>
@endsection
