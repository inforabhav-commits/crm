@extends('layouts.crm', ['title' => 'CRM Settings'])

@section('content')
    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <h2 class="h5 mb-1">CRM Master Settings</h2>
            <p class="text-muted">Configure reusable CRM master data for future lead, activity, and opportunity modules.</p>

            <div class="row g-3">
                @foreach ($types as $typeKey => $type)
                    <div class="col-md-6 col-xl-4">
                        <a class="card h-100 text-decoration-none border shadow-sm" href="{{ route('admin.crm-settings.show', $typeKey) }}">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between">
                                    <h3 class="h6 mb-0 text-dark">{{ $type['label'] }}</h3>
                                    <span class="badge text-bg-light">{{ $counts[$type['type']] ?? 0 }}</span>
                                </div>
                                <p class="text-muted mb-0 mt-2">Manage active values, order, and defaults.</p>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection
