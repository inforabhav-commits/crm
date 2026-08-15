@extends('layouts.crm', ['title' => 'Notifications'])

@section('content')
    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h2 class="h5 mb-0">Notifications</h2>
                <form method="post" action="{{ route('notifications.mark-all-read') }}">
                    @csrf
                    @method('patch')
                    <button class="btn btn-sm btn-outline-primary" type="submit">Mark all read</button>
                </form>
            </div>

            <div class="list-group list-group-flush">
                @forelse ($notifications as $notification)
                    @php($data = $notification->data)
                    <div class="list-group-item px-0">
                        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                            <div>
                                <div class="fw-semibold">
                                    @unless($notification->read_at)
                                        <span class="badge text-bg-primary rounded-1 me-1">Unread</span>
                                    @endunless
                                    {{ $data['title'] ?? 'CRM notification' }}
                                </div>
                                <div class="text-muted">{{ $data['message'] ?? '' }}</div>
                                <small class="text-muted">{{ $notification->created_at?->diffForHumans() }}</small>
                            </div>
                            <div class="d-flex gap-2">
                                @if (! empty($data['url']))
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ $data['url'] }}">Open</a>
                                @endif
                                @unless($notification->read_at)
                                    <form method="post" action="{{ route('notifications.mark-read', $notification->id) }}">
                                        @csrf
                                        @method('patch')
                                        <button class="btn btn-sm btn-primary" type="submit">Mark read</button>
                                    </form>
                                @endunless
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-muted">No notifications found.</div>
                @endforelse
            </div>

            <div class="mt-3">{{ $notifications->links() }}</div>
        </div>
    </section>
@endsection
