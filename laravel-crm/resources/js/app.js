import './bootstrap';
import { JustCallDialer } from '@justcall/justcall-dialer-sdk';

const callForms = '[data-click-to-call-form]';
let justCallDialer = null;
let dialerReadyPromise = null;
let pendingNumber = null;

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

function ensureDialer() {
    showDialerPanel();

    if (justCallDialer) {
        return dialerReadyPromise;
    }

    setDialerState('loading', 'Connecting', 'Loading JustCall dialer...');

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
            panel.hidden = true;
        });
    }
});

document.addEventListener('submit', async (event) => {
    if (! event.target.matches(callForms)) {
        return;
    }

    event.preventDefault();

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
            throw new Error(payload.message || 'Unable to start this call.');
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
