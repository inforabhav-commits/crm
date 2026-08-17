<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} | Laravel CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('assets/css/crm.css') }}" rel="stylesheet">
</head>
<body>
<div class="crm-shell d-flex">
    <aside class="crm-sidebar p-3">
        <div class="d-flex align-items-center gap-2 mb-4">
            <span class="badge crm-brand-mark rounded-1">CRM</span>
            <div>
                <div class="fw-semibold crm-brand-title">Laravel CRM</div>
                <small class="crm-brand-subtitle">Sales workspace</small>
            </div>
        </div>

        @php
            $items = [
                ['label' => 'Dashboard', 'route' => route('dashboard')],
                ['section' => 'CRM'],
                ['label' => 'Leads', 'route' => route('leads.index')],
                ['label' => 'Customers', 'route' => route('customers.index')],
                ['label' => 'Contacts', 'route' => route('contacts.index')],
                ['label' => 'Opportunities', 'route' => route('opportunities.index')],
                ['label' => 'Activities', 'route' => route('activities.index')],
                ['section' => 'Communication'],
                ['label' => 'Calls', 'route' => route('calls.index')],
                ['section' => 'Reports'],
                ['label' => 'Reports', 'route' => route('reports.index')],
                ['label' => 'Import / Export', 'route' => route('import-export.index')],
                ['section' => 'Administration'],
                ['label' => 'Users', 'route' => route('admin.users.index')],
                ['label' => 'Roles & Permissions', 'route' => route('admin.roles.index')],
                ['label' => 'Teams', 'route' => route('admin.teams.index')],
                ['label' => 'Workflow Rules', 'route' => route('admin.workflows.index')],
                ['label' => 'Operational Health', 'route' => route('admin.health')],
                ['label' => 'CRM Settings', 'route' => route('admin.crm-settings.index')],
                ['label' => 'Audit Log', 'route' => route('admin.audit-logs.index')],
                ['label' => 'JustCall Settings', 'route' => route('admin.justcall-settings.index')],
            ];
        @endphp

        <nav class="crm-nav d-grid">
            @foreach ($items as $item)
                @if (isset($item['section']))
                    <div class="nav-section mt-3 mb-1">{{ $item['section'] }}</div>
                @else
                    <a class="crm-nav-link d-block px-3 py-2 {{ request()->url() === $item['route'] ? 'active' : '' }}" href="{{ $item['route'] }}">{{ $item['label'] }}</a>
                @endif
            @endforeach
        </nav>
    </aside>

    <div class="crm-main flex-grow-1">
        <header class="crm-topbar px-4 py-3 d-flex align-items-center justify-content-between">
            <div>
                <h1 class="h4 mb-1 crm-page-title">{{ $title ?? 'Dashboard' }}</h1>
                <small class="crm-page-subtitle">Sales workspace · Secure CRM operations</small>
            </div>
            <div class="crm-topbar-actions d-flex align-items-center gap-3">
                <a class="btn btn-outline-primary btn-sm" href="{{ route('notifications.index') }}">
                    Notifications
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
<div class="screen-pop card border-0 shadow-lg" id="screen-pop" aria-live="polite">
    <div class="card-body">
        <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
            <div>
                <div class="text-muted small">Incoming Call</div>
                <h2 class="h6 mb-0" id="screen-pop-title">Unknown Caller</h2>
            </div>
            <button class="btn-close" type="button" id="screen-pop-dismiss" aria-label="Dismiss"></button>
        </div>
        <div class="small text-muted mb-2" id="screen-pop-phone"></div>
        <div class="small mb-3" id="screen-pop-summary"></div>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-primary" id="screen-pop-open" href="#" style="display:none">Open Record</a>
            <a class="btn btn-sm btn-outline-secondary" id="screen-pop-search" href="#">Search Leads</a>
        </div>
    </div>
</div>
<script>
    document.addEventListener('submit', function (event) {
        if (! event.target.matches('[data-click-to-call-form]')) {
            return;
        }

        const button = event.target.querySelector('[data-click-to-call-button]');
        if (! button) {
            return;
        }

        button.disabled = true;
        button.dataset.originalText = button.textContent;
        button.textContent = 'Launching...';
    });

    (function () {
        const box = document.getElementById('screen-pop');
        const title = document.getElementById('screen-pop-title');
        const phone = document.getElementById('screen-pop-phone');
        const summary = document.getElementById('screen-pop-summary');
        const open = document.getElementById('screen-pop-open');
        const search = document.getElementById('screen-pop-search');
        const dismiss = document.getElementById('screen-pop-dismiss');
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        let currentDismissUrl = null;

        function show(data) {
            currentDismissUrl = data.dismiss_url;
            phone.textContent = data.caller_phone || 'Unknown number';
            search.href = data.search_url;
            open.style.display = 'none';
            open.removeAttribute('href');

            if (data.record) {
                title.textContent = data.record.name;
                summary.textContent = data.record.type + (data.record.owner ? ' · Owner: ' + data.record.owner : '') + (data.record.recent_activity ? ' · Recent: ' + data.record.recent_activity : '');
                open.href = data.record.url;
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
        }

        function hide() {
            box.style.display = 'none';
            currentDismissUrl = null;
        }

        async function poll() {
            try {
                const response = await fetch('{{ route('screen-pop.current') }}', {headers: {'Accept': 'application/json'}});
                if (! response.ok) {
                    hide();
                    return;
                }

                const payload = await response.json();
                payload.screen_pop ? show(payload.screen_pop) : hide();
            } catch (error) {
                hide();
            }
        }

        dismiss.addEventListener('click', async function () {
            if (currentDismissUrl) {
                await fetch(currentDismissUrl, {method: 'POST', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'}});
            }
            hide();
        });

        poll();
        window.setInterval(poll, 10000);
    })();
</script>
</body>
</html>
