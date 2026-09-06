<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} | CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('assets/css/crm.css') }}" rel="stylesheet">
</head>
<body>
<div class="crm-shell d-flex">
    <aside class="crm-sidebar p-3">
        <div class="crm-brand d-flex align-items-center gap-2">
            <span class="badge crm-brand-mark rounded-1">CRM</span>
            <div>
                <div class="fw-semibold crm-brand-title">CRM</div>
                <small class="crm-brand-subtitle">Sales workspace</small>
            </div>
        </div>

        @php
            $groups = [
                ['items' => [
                    ['label' => 'Dashboard', 'route' => route('dashboard'), 'icon' => 'layout-dashboard'],
                ]],
                ['section' => 'CRM', 'items' => [
                    ['label' => 'Leads', 'route' => route('leads.index'), 'permission' => 'leads.view', 'icon' => 'users'],
                    ['label' => 'Customers', 'route' => route('customers.index'), 'permission' => 'customers.view', 'icon' => 'contact'],
                    ['label' => 'Contacts', 'route' => route('contacts.index'), 'permission' => 'contacts.view', 'icon' => 'user-round'],
                    ['label' => 'Opportunities', 'route' => route('opportunities.index'), 'permission' => 'opportunities.view', 'icon' => 'briefcase-business'],
                    ['label' => 'Activities', 'route' => route('activities.index'), 'permission' => 'activities.view', 'icon' => 'clipboard-list'],
                ]],
                ['section' => 'Communication', 'items' => [
                    ['label' => 'Calls', 'route' => route('calls.index'), 'permission' => 'calls.view', 'icon' => 'phone'],
                ]],
                ['section' => 'Reports', 'items' => [
                    ['label' => 'Reports', 'route' => route('reports.index'), 'permission' => 'reports.view', 'icon' => 'chart-no-axes-combined'],
                    ['label' => 'Import / Export', 'route' => route('import-export.index'), 'any' => ['import.leads', 'import.customers', 'export.crm'], 'icon' => 'download'],
                ]],
                ['section' => 'Administration', 'items' => [
                    ['label' => 'Users', 'route' => route('admin.users.index'), 'permission' => 'users.view', 'icon' => 'users'],
                    ['label' => 'Roles & Permissions', 'route' => route('admin.roles.index'), 'permission' => 'roles.view', 'icon' => 'shield-check'],
                    ['label' => 'Teams', 'route' => route('admin.teams.index'), 'permission' => 'teams.view', 'icon' => 'users-round'],
                    ['label' => 'Workflow Rules', 'route' => route('admin.workflows.index'), 'permission' => 'workflows.view', 'icon' => 'workflow'],
                    ['label' => 'Operational Health', 'route' => route('admin.health'), 'permission' => 'ops.view', 'icon' => 'activity'],
                    ['label' => 'CRM Settings', 'route' => route('admin.crm-settings.index'), 'permission' => 'crm_settings.view', 'icon' => 'settings'],
                    ['label' => 'Audit Log', 'route' => route('admin.audit-logs.index'), 'permission' => 'audit.view', 'icon' => 'file-search'],
                    ['label' => 'JustCall Settings', 'route' => route('admin.justcall-settings.index'), 'permission' => 'justcall.view', 'icon' => 'phone-call'],
                ]],
            ];

            $canSeeItem = function (array $item) {
                if (isset($item['permission']) && ! auth()->user()->can($item['permission'])) {
                    return false;
                }

                if (isset($item['any']) && ! collect($item['any'])->contains(fn ($permission) => auth()->user()->can($permission))) {
                    return false;
                }

                return true;
            };
        @endphp

        <nav class="crm-nav d-grid">
            @foreach ($groups as $group)
                @php
                    $visibleItems = collect($group['items'])->filter($canSeeItem);
                @endphp
                @if ($visibleItems->isNotEmpty())
                    @if (isset($group['section']))
                        <div class="nav-section mt-3 mb-1">{{ $group['section'] }}</div>
                    @endif
                    @foreach ($visibleItems as $item)
                        <a class="crm-nav-link d-flex align-items-center gap-2 {{ request()->url() === $item['route'] ? 'active' : '' }}" href="{{ $item['route'] }}">
                            <i data-lucide="{{ $item['icon'] ?? 'circle' }}" aria-hidden="true"></i>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                @endif
            @endforeach
        </nav>
    </aside>

    <div class="crm-main flex-grow-1">
        <header class="crm-topbar px-4 py-3 d-flex align-items-center justify-content-between">
            <div>
                <h1 class="h4 mb-1 crm-page-title">{{ $title ?? 'Dashboard' }}</h1>
                <small class="crm-page-subtitle">Sales workspace - Secure CRM operations</small>
            </div>
            <div class="crm-topbar-actions d-flex align-items-center gap-3">
                <a class="btn btn-outline-primary btn-sm" href="{{ route('notifications.index') }}">
                    <i data-lucide="bell" aria-hidden="true"></i>
                    <span>Notifications</span>
                    @if (($unreadCount = auth()->user()->unreadNotifications()->count()) > 0)
                        <span class="badge text-bg-primary rounded-1 ms-1">{{ $unreadCount }}</span>
                    @endif
                </a>
                <span class="crm-user-chip">{{ auth()->user()->name }}</span>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-outline-secondary btn-sm" type="submit">Logout</button>
                </form>
            </div>
        </header>

        <main class="crm-content p-4">
            @yield('content')
        </main>
    </div>
</div>

@can('calls.initiate')
<div class="justcall-dialer-shell" id="justcall-dialer-shell" data-state="idle" data-private="{{ app(\App\Services\PhonePrivacyService::class)->canViewFullPhone(auth()->user()) ? '0' : '1' }}" aria-live="polite">
    <div class="justcall-dialer-panel" id="justcall-dialer-panel" hidden>
        <div class="justcall-dialer-header">
            <div>
                <div class="justcall-dialer-kicker">JustCall Dialer</div>
                <div class="justcall-dialer-title" id="justcall-dialer-title">Ready</div>
            </div>
            <button class="justcall-dialer-close" id="justcall-dialer-close" type="button" aria-label="Close JustCall dialer">
                <i data-lucide="x" aria-hidden="true"></i>
            </button>
        </div>
        <div class="justcall-dialer-status" id="justcall-dialer-status">Open the dialer before placing a call.</div>
        <div class="p-3" id="crm-private-dialer" hidden>
            <div id="crm-dialer-number"></div>
            <div id="crm-dialer-direction" class="text-muted small"></div>
            <div id="crm-dialer-duration" class="small">00:00</div>
            <div class="small mt-2">Notes: <span id="crm-dialer-notes">—</span></div>
            <div class="small">Disposition: <span id="crm-dialer-disposition">—</span></div>
            <a class="btn btn-sm btn-outline-primary mt-2" id="crm-dialer-history" hidden>Update notes / disposition</a>
            <p class="small text-muted mt-2 mb-0">Answer and end controls are unavailable in this CRM integration.</p>
        </div>
        @if (app(\App\Services\PhonePrivacyService::class)->canViewFullPhone(auth()->user()))
            <div class="justcall-dialer-frame" id="justcall-dialer"></div>
        @endif
    </div>
</div>

<div class="call-dock" id="call-dock" data-state="idle" aria-live="polite">
    <button class="call-dock-button" id="call-dock-button" type="button" aria-label="Call status">
        <i data-lucide="phone" aria-hidden="true"></i>
        <span class="call-dock-state" id="call-dock-state">Idle</span>
    </button>
    <div class="screen-pop card border-0 shadow-lg" id="screen-pop">
        <div class="card-body">
            <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                <div>
                    <div class="text-muted small" id="screen-pop-kicker">Call Status</div>
                    <h2 class="h6 mb-0" id="screen-pop-title">Idle</h2>
                </div>
                <button class="btn-close" type="button" id="screen-pop-dismiss" aria-label="Dismiss"></button>
            </div>
            <div class="small text-muted mb-2" id="screen-pop-phone">No active call</div>
            <div class="small mb-2" id="screen-pop-summary"></div>
            <div class="small text-muted mb-3" id="screen-pop-timer" style="display:none">00:00</div>
            <div class="d-flex gap-2">
                <a class="btn btn-sm btn-primary" id="screen-pop-open" href="#" style="display:none">Open Customer</a>
                <a class="btn btn-sm btn-outline-secondary" id="screen-pop-search" href="#" style="display:none">Search Leads</a>
                <a class="btn btn-sm btn-outline-secondary" id="screen-pop-history" href="#" style="display:none">Notes / Disposition</a>
            </div>
        </div>
    </div>
</div>
@endcan

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
@vite(['resources/js/app.js'])
<script>
    if (window.lucide) {
        window.lucide.createIcons();
    }

    @can('calls.initiate')
    (function () {
        const dock = document.getElementById('call-dock');
        const dockButton = document.getElementById('call-dock-button');
        const dockState = document.getElementById('call-dock-state');
        const box = document.getElementById('screen-pop');
        const kicker = document.getElementById('screen-pop-kicker');
        const title = document.getElementById('screen-pop-title');
        const phone = document.getElementById('screen-pop-phone');
        const summary = document.getElementById('screen-pop-summary');
        const timer = document.getElementById('screen-pop-timer');
        const open = document.getElementById('screen-pop-open');
        const search = document.getElementById('screen-pop-search');
        const dismiss = document.getElementById('screen-pop-dismiss');
        const history = document.getElementById('screen-pop-history');
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        let currentDismissUrl = null;
        let connectedSince = null;

        function labelFor(status) {
            if (status === 'ringing') return 'Ringing';
            if (status === 'answered' || status === 'connected') return 'Connected';
            if (status === 'missed') return 'Missed';
            if (status === 'completed') return 'Ended';
            if (status === 'failed') return 'Failed';
            return 'Idle';
        }

        function setState(status) {
            const label = labelFor(status);
            dock.dataset.state = label.toLowerCase();
            dockState.textContent = label;
            kicker.textContent = label === 'Idle' ? 'Call Status' : 'Incoming Call';
        }

        function updateTimer() {
            if (! connectedSince) {
                timer.style.display = 'none';
                return;
            }

            const elapsed = Math.max(0, Math.floor((Date.now() - connectedSince) / 1000));
            const minutes = String(Math.floor(elapsed / 60)).padStart(2, '0');
            const seconds = String(elapsed % 60).padStart(2, '0');
            timer.textContent = minutes + ':' + seconds;
            timer.style.display = '';
        }

        function show(data) {
            if (document.getElementById('justcall-dialer-shell')?.dataset.private === '1') {
                window.dispatchEvent(new CustomEvent('crm-call-update', {detail: data}));
                setState(data.status);
                box.style.display = 'none';
                return;
            }
            currentDismissUrl = data.dismiss_url;
            connectedSince = data.connected_since ? new Date(data.connected_since).getTime() : null;
            setState(data.status);
            phone.textContent = data.masked_number || 'Unknown number';
            history.style.display = data.history_url ? '' : 'none';
            if (data.history_url) history.href = data.history_url;
            if (['completed', 'missed', 'failed'].includes(data.status)) connectedSince = null;
            search.href = data.search_url;
            search.style.display = '';
            open.textContent = 'Open Customer';
            open.style.display = 'none';
            open.removeAttribute('href');

            if (data.record) {
                title.textContent = data.record.name;
                summary.textContent = data.record.type + (data.record.owner ? ' - Owner: ' + data.record.owner : '') + (data.record.recent_activity ? ' - Recent: ' + data.record.recent_activity : '');
                open.href = data.record.url;
                open.textContent = data.record.open_label || 'Open Customer';
                open.style.display = '';
            } else if (data.match_state === 'ambiguous') {
                title.textContent = 'Possible Matches';
                summary.textContent = 'Multiple ' + (data.ambiguous_type || 'CRM') + ' records match this caller.';
            } else if (data.match_state === 'restricted') {
                title.textContent = 'Matched Caller';
                summary.textContent = 'A CRM record matched, but it is outside your current access.';
            } else {
                title.textContent = 'Unknown Caller';
                summary.textContent = 'No safe CRM match found.';
            }

            box.style.display = 'block';
            updateTimer();
            if (['completed', 'missed', 'failed'].includes(data.status)) {
                timer.textContent = String(data.duration_seconds || 0) + ' seconds';
                timer.style.display = '';
            }
        }

        function idle() {
            window.dispatchEvent(new CustomEvent('crm-call-update', {detail: null}));
            setState('idle');
            title.textContent = 'Idle';
            phone.textContent = 'No active call';
            summary.textContent = '';
            open.style.display = 'none';
            search.style.display = 'none';
            history.style.display = 'none';
            connectedSince = null;
            currentDismissUrl = null;
            updateTimer();
        }

        dockButton.addEventListener('click', function () {
            if (document.getElementById('justcall-dialer-shell')?.dataset.private === '1') {
                const panel = document.getElementById('justcall-dialer-panel');
                panel.hidden = !panel.hidden;
                return;
            }
            box.style.display = box.style.display === 'block' ? 'none' : 'block';
        });

        async function poll() {
            try {
                const response = await fetch('{{ route('screen-pop.current') }}', {headers: {'Accept': 'application/json'}});
                if (! response.ok) {
                    idle();
                    return;
                }

                const payload = await response.json();
                payload.screen_pop ? show(payload.screen_pop) : idle();
            } catch (error) {
                idle();
            }
        }

        dismiss.addEventListener('click', async function () {
            if (currentDismissUrl) {
                await fetch(currentDismissUrl, {method: 'POST', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'}});
            }
            box.style.display = 'none';
            idle();
        });

        poll();
        window.setInterval(poll, 10000);
        window.setInterval(updateTimer, 1000);
    })();
    @endcan
</script>
</body>
</html>
