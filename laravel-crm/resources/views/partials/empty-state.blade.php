<div class="crm-empty">
    <div class="crm-empty-icon" aria-hidden="true"><i data-lucide="{{ $icon ?? 'inbox' }}"></i></div>
    <div class="crm-empty-title">{{ $title ?? 'Nothing here yet' }}</div>
    <div class="crm-empty-text">{{ $message ?? 'Records will appear here when they are available.' }}</div>
    @isset($actionUrl)
        <a class="btn btn-sm btn-primary mt-3" href="{{ $actionUrl }}">
            @isset($actionIcon)<i data-lucide="{{ $actionIcon }}" aria-hidden="true"></i>@endisset
            <span>{{ $actionLabel ?? 'Create' }}</span>
        </a>
    @endisset
</div>
