@extends('layouts.crm', ['title' => 'JustCall Webhook Inbox'])

@section('content')
    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h2 class="h5 mb-0">JustCall Webhook Inbox</h2>
                <div class="d-flex flex-wrap gap-2">
                    @can('justcall.manage')
                        <form method="POST" action="{{ route('admin.justcall-webhooks.process') }}">
                            @csrf
                            <button class="btn btn-sm btn-primary" type="submit">Process Pending</button>
                        </form>
                    @endcan
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.justcall-settings.index') }}">Settings</a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Received</th><th>Event</th><th>External ID</th><th>Status</th><th>Normalized</th><th>Attempts</th><th>Failure</th></tr></thead>
                    <tbody>
                    @forelse ($entries as $entry)
                        <tr>
                            <td>{{ $entry->received_at?->format('Y-m-d H:i:s') }}</td>
                            <td>{{ $entry->event_type ?? '-' }}</td>
                            <td>{{ $entry->external_id ?? '-' }}</td>
                            <td><span class="badge text-bg-secondary rounded-1">{{ $entry->processing_status }}</span></td>
                            <td>{{ $entry->normalized_event_type ?? '-' }}</td>
                            <td>{{ $entry->attempt_count }}</td>
                            <td>{{ $entry->failure_summary ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td class="text-muted" colspan="7">No JustCall webhooks received.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{ $entries->links() }}
        </div>
    </section>
@endsection
