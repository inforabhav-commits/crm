@extends('layouts.crm', ['title' => 'Contact Detail'])

@section('content')
    @php
        $phonePrivacy = app(\App\Services\PhonePrivacyService::class);
    @endphp
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h2 class="h5 mb-1">{{ $contact->name }}</h2>
                    <p class="text-muted mb-0">{{ $contact->customer?->name ?? 'No customer' }}</p>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-outline-secondary" href="{{ route('contacts.index') }}">Back</a>
                    @can('calls.initiate')
                        @if ($canShowCallAction)
                            <form method="post" action="{{ route('contacts.justcall.call', $contact) }}" data-click-to-call-form target="_blank" rel="noopener">
                                @csrf
                                <button class="btn btn-outline-primary" type="submit" data-click-to-call-button>Call</button>
                            </form>
                        @endif
                    @endcan
                    @can('activities.create')
                        <a class="btn btn-outline-primary" href="{{ route('activities.create', ['contact_id' => $contact->id]) }}">Add Activity</a>
                    @endcan
                    @can('contacts.edit')
                        <a class="btn btn-primary" href="{{ route('contacts.edit', $contact) }}">Edit</a>
                    @endcan
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-3"><div class="text-muted small">Customer</div><div><a href="{{ route('customers.show', $contact->customer) }}">{{ $contact->customer?->name }}</a></div></div>
                <div class="col-md-3"><div class="text-muted small">Title</div><div>{{ $contact->title ?: '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Status</div><div>{{ $contact->is_active ? 'Active' : 'Inactive' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Primary</div><div>{{ $contact->is_primary ? 'Yes' : 'No' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Email</div><div>{{ $contact->email ?: '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Phone</div><div>{{ $phonePrivacy->display($contact->phone, auth()->user()) }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Mobile</div><div>{{ $phonePrivacy->display($contact->mobile, auth()->user()) }}</div></div>
                <div class="col-md-3">
                    <div class="text-muted small">Source Lead</div>
                    <div>
                        @if ($contact->sourceLead)
                            <a href="{{ route('leads.show', $contact->sourceLead) }}">{{ $contact->sourceLead->name }}</a>
                        @else
                            -
                        @endif
                    </div>
                </div>
                <div class="col-12"><div class="text-muted small">Notes</div><div class="border rounded p-3 bg-light">{{ $contact->notes ?: 'No notes recorded.' }}</div></div>
            </div>
        </div>
    </section>

    <section class="card border-0 shadow-sm mt-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Activities</h2>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Activity</th><th>Type</th><th>Owner</th><th>Due</th><th class="text-end">Action</th></tr></thead>
                    <tbody>
                    @forelse ($contact->activities->take(10) as $activity)
                        <tr>
                            <td>{{ $activity->subject }}</td>
                            <td>{{ $activity->type?->name ?? '-' }}</td>
                            <td>{{ $activity->assignedUser?->name ?? '-' }}</td>
                            <td>{{ $activity->due_at?->format('Y-m-d H:i') }}</td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="{{ route('activities.show', $activity) }}">View</a></td>
                        </tr>
                    @empty
                        <tr><td class="text-muted" colspan="5">No activities recorded.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="card border-0 shadow-sm mt-4">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h2 class="h5 mb-0">Call History</h2>
                @can('calls.view')
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('calls.index', ['match' => 'matched']) }}">View Calls</a>
                @endcan
            </div>
            @include('call_logs._table', ['callLogs' => $callLogs])
        </div>
    </section>
@endsection
