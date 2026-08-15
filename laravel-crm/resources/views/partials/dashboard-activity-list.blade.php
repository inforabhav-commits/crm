@forelse ($activities as $activity)
    <div class="border-bottom pb-2 mb-2">
        <a class="fw-semibold" href="{{ route('activities.show', $activity) }}">{{ $activity->subject }}</a>
        <div class="small text-muted">
            {{ $activity->type?->name ?? 'Activity' }} -
            {{ ucfirst($activity->status) }} -
            {{ $activity->due_at?->format('Y-m-d H:i') ?? '-' }} -
            {{ $activity->assignedUser?->name ?? '-' }}
        </div>
    </div>
@empty
    <div class="text-muted">No activities found.</div>
@endforelse
