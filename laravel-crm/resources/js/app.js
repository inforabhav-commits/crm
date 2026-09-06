import './bootstrap';

const callForms = '[data-click-to-call-form]';
let justCallDialer = null;
let dialerReadyPromise = null;
let pendingNumber = null;
let privateCall = null;
let minimizedCallId = null;

function renderPrivateCall(payload) {
    privateCall = payload;
    document.getElementById('crm-private-dialer').hidden = false;
    const labels = {answered: 'Connected', completed: 'Ended', ringing: 'Ringing', missed: 'Missed', failed: 'Failed', calling: 'Calling', idle: 'Idle'};
    setDialerState(payload.status || 'idle', payload.record?.name || 'Unknown Caller', payload.message || labels[payload.status] || 'Incoming');
    document.getElementById('crm-dialer-number').textContent = payload.masked_number || '';
    document.getElementById('crm-dialer-direction').textContent = payload.direction === 'outbound' ? 'Outgoing' : 'Incoming';
    document.getElementById('crm-dialer-notes').textContent = payload.notes || '—';
    document.getElementById('crm-dialer-disposition').textContent = payload.disposition || '—';
    const history = document.getElementById('crm-dialer-history');
    history.hidden = !payload.history_url;
    if (payload.history_url) history.href = payload.history_url;
    if (!payload.id || payload.id !== minimizedCallId) showDialerPanel();
    updatePrivateDuration();
}

function updatePrivateDuration() {
    if (!privateCall) return;
    const connected = ['answered', 'connected'].includes(privateCall.status);
    const seconds = connected && privateCall.connected_since ? Math.max(0, Math.floor((Date.now() - Date.parse(privateCall.connected_since)) / 1000)) : (privateCall.duration_seconds || 0);
    document.getElementById('crm-dialer-duration').textContent = String(Math.floor(seconds / 60)).padStart(2, '0') + ':' + String(seconds % 60).padStart(2, '0');
}
window.addEventListener('crm-call-update', event => {
    if (event.detail) renderPrivateCall(event.detail);
    else if (privateCall?.id) {
        privateCall = null;
        setDialerState('idle', 'Idle', 'No active call');
        document.getElementById('crm-private-dialer').hidden = true;
    }
});
window.setInterval(updatePrivateDuration, 1000);

function dialerElements() {
    return {
        shell: document.getElementById('justcall-dialer-shell'),
        panel: document.getElementById('justcall-dialer-panel'),
        title: document.getElementById('justcall-dialer-title'),
        status: document.getElementById('justcall-dialer-status'),
        close: document.getElementById('justcall-dialer-close'),
    };
}

function setDialerState(state, title, status) {
    const elements = dialerElements();
    if (! elements.shell) {
        return;
    }

    elements.shell.dataset.state = state;
    if (elements.title) {
        elements.title.textContent = title;
    }
    if (elements.status) {
        elements.status.textContent = status;
    }
}

function showDialerPanel() {
    const { panel } = dialerElements();
    if (panel) {
        panel.hidden = false;
    }
}

async function ensureDialer() {
    showDialerPanel();

    if (justCallDialer) {
        return dialerReadyPromise;
    }

    setDialerState('loading', 'Connecting', 'Loading JustCall dialer...');

    const { JustCallDialer } = await import('@justcall/justcall-dialer-sdk');
    justCallDialer = new JustCallDialer({
        dialerId: 'justcall-dialer',
        onLogin: async () => {
            setDialerState('ready', 'Connected', 'JustCall is connected.');
            if (pendingNumber) {
                const number = pendingNumber;
                pendingNumber = null;
                justCallDialer.dialNumber(number);
            }
        },
        onLogout: () => {
            setDialerState('login', 'Sign in required', 'Sign in to JustCall in this panel before dialing.');
        },
        onReady: () => {
            setDialerState('ready', 'Ready', 'JustCall dialer is ready.');
        },
    });

    justCallDialer.on('call-ringing', () => {
        setDialerState('ringing', 'Ringing', 'Outbound call is ringing.');
    });
    justCallDialer.on('call-answered', () => {
        setDialerState('connected', 'Connected', 'Call connected.');
    });
    justCallDialer.on('call-ended', () => {
        setDialerState('ended', 'Call ended', 'The call has ended.');
    });

    dialerReadyPromise = justCallDialer.ready()
        .then(() => justCallDialer)
        .catch((error) => {
            justCallDialer = null;
            dialerReadyPromise = null;
            throw error;
        });

    return dialerReadyPromise;
}

async function dialAuthorizedNumber(payload) {
    const dialer = await ensureDialer();
    setDialerState('dialing', 'Dialing', 'Calling ' + (payload.display_phone || payload.record?.name || 'selected record') + '.');

    const loggedIn = await dialer.isLoggedIn();
    if (! loggedIn) {
        pendingNumber = payload.number;
        setDialerState('login', 'Sign in required', 'Sign in to JustCall in this panel to place this call.');
        return;
    }

    dialer.dialNumber(payload.number);
}

function restoreButton(button) {
    if (! button) {
        return;
    }

    button.disabled = false;
    if (button.dataset.originalHtml) {
        button.innerHTML = button.dataset.originalHtml;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const { close, panel } = dialerElements();
    if (close && panel) {
        close.addEventListener('click', () => {
            minimizedCallId = privateCall?.id;
            panel.hidden = true;
        });
    }
});

document.addEventListener('submit', async (event) => {
    if (! event.target.matches(callForms)) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();
    showDialerPanel();
    setDialerState('calling', 'Calling', 'Requesting call...');

    const form = event.target;
    const button = form.querySelector('[data-click-to-call-button]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    if (button) {
        button.disabled = true;
        button.dataset.originalHtml = button.dataset.originalHtml || button.innerHTML;
        button.textContent = 'Dialing...';
    }

    try {
        const response = await fetch(form.action, {
            method: form.method || 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const payload = await response.json();
        if (! response.ok || ! payload.ok) {
            if (payload.masked_number) {
                renderPrivateCall(payload);
                return;
            }
            setDialerState('failed', payload.record?.name || 'Call failed', [payload.masked_number, payload.message || 'Unable to start this call.'].filter(Boolean).join(' — '));
            return;
        }

        await dialAuthorizedNumber(payload);
    } catch (error) {
        showDialerPanel();
        setDialerState('error', 'Call failed', error.message || 'Unable to start this call.');
    } finally {
        restoreButton(button);
        if (window.lucide) {
            window.lucide.createIcons();
        }
    }
});
