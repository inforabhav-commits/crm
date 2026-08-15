document.addEventListener('DOMContentLoaded', function () {
    var root = document.querySelector('[data-website-call]');
    if (!root) {
        return;
    }

    var button = root.querySelector('[data-start-website-call]');
    var statusTarget = root.querySelector('[data-call-status]');

    function setStatus(message) {
        if (statusTarget) {
            statusTarget.textContent = message;
        }
    }

    if (!button) {
        return;
    }

    button.disabled = true;
    setStatus('This website-only call flow is deprecated. Use the embedded JustCall Dialer SDK page instead.');
    console.warn('Legacy website_call.js loaded. The legacy AI voice call flow has been deprecated.');
});
