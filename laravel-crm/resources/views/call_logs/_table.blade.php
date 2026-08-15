<div class="table-responsive">
    <table class="table align-middle">
        <thead>
        <tr>
            <th>Date / Time</th>
            <th>Call</th>
            <th>Agent</th>
            <th>Related Record</th>
            <th>Phone</th>
            <th>Duration</th>
            <th class="text-end">Action</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($callLogs as $callLog)
            <tr>
                <td>{{ $callLog->occurredAt()?->format('Y-m-d H:i') ?? '-' }}</td>
                <td>
                    <div class="fw-semibold">{{ ucfirst($callLog->direction) }} {{ $callLog->status ? '- '.ucfirst($callLog->status) : '' }}</div>
                    @if ($callLog->disposition)
                        <small class="text-muted">{{ $callLog->disposition }}</small>
                    @endif
                    @if ($callLog->notes)
                        <div><small class="text-muted">{{ \Illuminate\Support\Str::limit($callLog->notes, 80) }}</small></div>
                    @endif
                    @if ($callLog->hasAvailableRecording())
                        <span class="badge text-bg-light border rounded-1 mt-1">Recording</span>
                    @endif
                </td>
                <td>{{ $callLog->user?->name ?? ($callLog->agent_external_id ?: '-') }}</td>
                <td>
                    @if ($callLog->contact)
                        <a href="{{ route('contacts.show', $callLog->contact) }}">{{ $callLog->contact->name }}</a>
                    @elseif ($callLog->customer)
                        <a href="{{ route('customers.show', $callLog->customer) }}">{{ $callLog->customer->name }}</a>
                    @elseif ($callLog->lead)
                        <a href="{{ route('leads.show', $callLog->lead) }}">{{ $callLog->lead->name }}</a>
                    @elseif ($callLog->opportunity)
                        <a href="{{ route('opportunities.show', $callLog->opportunity) }}">{{ $callLog->opportunity->name }}</a>
                    @else
                        <span class="text-muted">Unmatched</span>
                    @endif
                </td>
                <td>{{ $callLog->customer_number ?: ($callLog->direction === 'inbound' ? $callLog->from_number : $callLog->to_number) ?: '-' }}</td>
                <td>{{ $callLog->duration_seconds !== null ? gmdate('H:i:s', $callLog->duration_seconds) : '-' }}</td>
                <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="{{ route('calls.show', $callLog) }}">View</a></td>
            </tr>
        @empty
            <tr>
                <td class="text-muted" colspan="7">No calls recorded.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>
