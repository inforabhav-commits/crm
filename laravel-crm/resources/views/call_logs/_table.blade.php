<div class="table-responsive">
    @php
        $phonePrivacy = app(\App\Services\PhonePrivacyService::class);
    @endphp
    <table class="table crm-data-table align-middle">
        <thead>
        <tr>
            <th>Date / Time</th>
            <th>Call</th>
            <th>Agent</th>
            <th>Related Record</th>
            <th>Phone</th>
            <th>Duration</th>
            <th>Status</th>
            <th class="text-end">Action</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($callLogs as $callLog)
            @php
                $direction = $callLog->direction ?: 'unknown';
                $status = $callLog->status ?: 'unknown';
                $statusTone = 'secondary';
                if (in_array($status, ['completed', 'answered', 'connected'], true)) {
                    $statusTone = 'success';
                } elseif (in_array($status, ['missed', 'failed'], true)) {
                    $statusTone = 'danger';
                } elseif (in_array($status, ['ringing', 'initiated'], true)) {
                    $statusTone = 'warning';
                }
                $agentName = $callLog->user?->name ?? ($callLog->agent_external_id ?: '-');
                $agentInitial = $agentName !== '-' ? \Illuminate\Support\Str::of($agentName)->trim()->substr(0, 1)->upper() : '?';
            @endphp
            <tr>
                <td>
                    <div class="fw-semibold">{{ $callLog->occurredAt()?->format('Y-m-d') ?? '-' }}</div>
                    <small class="text-muted">{{ $callLog->occurredAt()?->format('H:i') ?? '' }}</small>
                </td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <span class="crm-call-icon" aria-hidden="true"><i data-lucide="{{ $direction === 'outbound' ? 'phone-outgoing' : ($direction === 'inbound' ? 'phone-incoming' : 'phone') }}"></i></span>
                        <div>
                            <div class="fw-semibold">{{ ucfirst($direction) }}</div>
                            @if ($callLog->disposition)
                                <small class="text-muted">{{ $callLog->disposition }}</small>
                            @endif
                        </div>
                    </div>
                    @if ($callLog->notes)
                        <div><small class="text-muted">{{ \Illuminate\Support\Str::limit($callLog->notes, 80) }}</small></div>
                    @endif
                    @if ($callLog->hasAvailableRecording())
                        <span class="badge text-bg-light border rounded-1 mt-1">Recording</span>
                    @endif
                </td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <span class="crm-agent-avatar">{{ $agentInitial }}</span>
                        <span>{{ $agentName }}</span>
                    </div>
                </td>
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
                <td><span class="crm-phone-value">{{ $phonePrivacy->callNumber($callLog, auth()->user()) }}</span></td>
                <td>{{ $callLog->duration_seconds !== null ? gmdate('H:i:s', $callLog->duration_seconds) : '-' }}</td>
                <td><span class="badge crm-status-badge crm-status-{{ $statusTone }}">{{ ucfirst($status) }}</span></td>
                <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="{{ route('calls.show', $callLog) }}">View</a></td>
            </tr>
        @empty
            <tr>
                <td colspan="8">
                    <div class="crm-empty crm-empty-calls">
                        <div class="crm-empty-icon" aria-hidden="true"><i data-lucide="phone"></i></div>
                        <div class="crm-empty-title">No calls yet</div>
                        <div class="crm-empty-text">Incoming and outgoing JustCall activity will appear here after calls are processed.</div>
                    </div>
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>
