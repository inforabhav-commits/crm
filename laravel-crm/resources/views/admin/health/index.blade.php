@extends('layouts.crm', ['title' => 'Operational Health'])

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h2 class="h5 mb-0">Operational Health</h2><div class="text-muted small">Safe application and integration checks. Checked {{ $checkedAt->format('Y-m-d H:i:s') }}.</div></div>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('dashboard') }}">Dashboard</a>
    </div>
    <div class="row g-3">
        @foreach ($checks as $check)
            <div class="col-md-6 col-xl-4">
                <section class="card border-0 shadow-sm h-100"><div class="card-body">
                    <div class="d-flex justify-content-between gap-2"><h3 class="h6 mb-2">{{ $check['label'] }}</h3><span class="badge {{ $check['ok'] ? 'text-bg-success' : 'text-bg-warning' }} rounded-1">{{ $check['ok'] ? 'OK' : 'Warning' }}</span></div>
                    <p class="mb-0">{{ $check['message'] }}</p>
                </div></section>
            </div>
        @endforeach
    </div>
@endsection
