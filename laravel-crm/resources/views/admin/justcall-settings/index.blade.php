@extends('layouts.crm', ['title' => 'JustCall Settings'])

@section('content')
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <section class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <h2 class="h5 mb-1 crm-page-title">JustCall Integration</h2>
                    <p class="text-muted mb-0">Manage connection readiness, mappings, webhooks, and call monitoring.</p>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.justcall-mappings.index') }}">User Mapping</a>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.justcall-webhooks.index') }}">Webhook Inbox</a>
                    @can('justcall.monitor')
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.justcall-monitoring.index') }}">Integration Monitoring</a>
                    @endcan
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
                    'Integration Status' => [$status['enabled'], $status['enabled'] ? 'Enabled' : 'Disabled'],
                    'API Key' => [$status['api_key_configured'], $status['api_key_configured'] ? 'Configured' : 'Not Configured'],
                    'API Secret' => [$status['api_secret_configured'], $status['api_secret_configured'] ? 'Configured' : 'Not Configured'],
                    'Webhook Secret' => [$status['webhook_secret_configured'], $status['webhook_secret_configured'] ? 'Configured' : 'Not Configured'],
                    'Credentials Ready' => [$status['credentials_configured'], $status['credentials_configured'] ? 'Ready' : 'Not Ready'],
                ] as $label => $value)
                    <div class="col-sm-6 col-xl-4">
                        <div class="crm-status-card border rounded-1 p-3 h-100">
                            <div class="status-label">{{ $label }}</div>
                            <div class="status-value mt-2"><span class="badge {{ $value[0] ? 'text-bg-success' : 'text-bg-warning' }} rounded-1">{{ $value[1] }}</span></div>
                        </div>
                    </div>
                @endforeach
                <div class="col-sm-6 col-xl-4">
                    <div class="crm-status-card border rounded-1 p-3 h-100">
                        <div class="status-label">Base URL</div>
                        <div class="status-value mt-2 text-break">{{ $status['base_url'] }}</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <div class="crm-status-card border rounded-1 p-3 h-100">
                        <div class="status-label">Auth Mode</div>
                        <div class="status-value mt-2">{{ strtoupper($status['auth_mode']) }}</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <div class="crm-status-card border rounded-1 p-3 h-100">
                        <div class="status-label">Secret Values</div>
                        <div class="status-value mt-2"><span class="badge text-bg-primary rounded-1">Masked server-side</span></div>
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
