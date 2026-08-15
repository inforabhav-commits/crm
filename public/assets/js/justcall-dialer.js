import { JustCallDialer } from '../vendor/justcall-dialer-sdk.mjs';

var root = document.querySelector('[data-justcall-dialer]');
if (root) {
    var statusTarget = root.querySelector('[data-dialer-status]');
    var callButton = root.querySelector('[data-sdk-call]');
    var directDialerButton = root.querySelector('[data-direct-dialer]');
    var customerId = root.getAttribute('data-customer-id');
    var currentPhoneNumber = null;
    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    var loadTimer = window.setTimeout(function () {
        enableFallback();
        setStatus('Embedded JustCall is still loading. Make sure the sales user is logged in to JustCall and microphone permission is allowed.');
    }, 8000);

    function setStatus(message) {
        if (statusTarget) {
            statusTarget.textContent = message;
        }
    }

    function logSdkEvent(payload) {
        return fetch('sdk_call_event.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(Object.assign({
                customer_id: customerId,
                customer_number: currentPhoneNumber
            }, payload))
        }).catch(function () {});
    }

    function fetchDialNumber() {
        return fetch('dial_number.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ customer_id: customerId })
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok || !payload.ok) {
                    throw new Error(payload.message || 'Customer number could not be prepared.');
                }
                return payload.phone_number;
            });
        });
    }

    function withTimeout(promise, milliseconds, message) {
        var timeoutId;
        var timeout = new Promise(function (_, reject) {
            timeoutId = window.setTimeout(function () {
                reject(new Error(message));
            }, milliseconds);
        });

        return Promise.race([promise, timeout]).finally(function () {
            window.clearTimeout(timeoutId);
        });
    }

    function enableFallback() {
        if (directDialerButton) {
            directDialerButton.hidden = false;
        }
    }

    var dialer = new JustCallDialer({
        dialerId: 'justcall-dialer',
        onLogin: function (data) {
            var user = data && data.user_info ? data.user_info : {};
            setStatus(user.email ? 'Logged in to JustCall as ' + user.email : 'Logged in to JustCall.');
            if (callButton) {
                callButton.disabled = false;
            }
        },
        onLogout: function () {
            setStatus('Log in to the embedded JustCall dialer, then send the number again.');
            if (callButton) {
                callButton.disabled = true;
            }
        },
        onReady: function () {
            setStatus('JustCall dialer ready. Checking login...');
        }
    });

    dialer.on('call-ringing', function (data) {
        setStatus('Call ringing...');
        logSdkEvent({
            status: 'ringing',
            call_sid: data && data.call_sid ? data.call_sid : '',
            direction: data && data.direction ? data.direction : 'outbound'
        });
    });

    dialer.on('call-answered', function (data) {
        setStatus('Call answered.');
        logSdkEvent({
            status: data && data.answered_status ? data.answered_status : 'answered',
            call_sid: data && data.call_sid ? data.call_sid : '',
            direction: data && data.direction ? data.direction : 'outbound'
        });
    });

    dialer.on('call-ended', function (data) {
        setStatus('Call ended. Waiting for JustCall webhook for recording and final details...');
        logSdkEvent({
            status: 'ended',
            call_sid: data && data.call_sid ? data.call_sid : '',
            direction: data && data.direction ? data.direction : 'outbound',
            duration: data && data.duration ? data.duration : null
        }).finally(function () {
            window.setTimeout(function () {
                window.location.reload();
            }, 1600);
        });
    });

    dialer.ready().then(function () {
        window.clearTimeout(loadTimer);
        enableFallback();
        return withTimeout(dialer.isLoggedIn(), 5000, 'Login check timed out');
    }).then(function (isLoggedIn) {
        if (isLoggedIn) {
            setStatus('JustCall dialer ready.');
            if (callButton) {
                callButton.disabled = false;
            }
        } else {
            setStatus('Log in to the embedded JustCall dialer, then send the number again.');
        }
    }).catch(function (error) {
        window.clearTimeout(loadTimer);
        enableFallback();
        if (error && error.message === 'Login check timed out') {
            setStatus('JustCall loaded, but login status did not respond. Log in inside the embedded dialer and try again.');
            if (callButton) {
                callButton.disabled = false;
            }
            return;
        }
        setStatus('JustCall dialer could not be loaded: ' + (error && error.message ? error.message : 'Unknown error') + '. Check JustCall login and browser permissions.');
    });

    if (callButton) {
        callButton.addEventListener('click', function () {
            callButton.disabled = true;
            setStatus('Preparing customer call...');
            fetchDialNumber().then(function (phoneNumber) {
                currentPhoneNumber = phoneNumber;
                return withTimeout(dialer.ready(), 15000, 'Dialer load timed out').then(function () {
                    return dialer.dialNumber(phoneNumber);
                });
            }).then(function () {
                setStatus('Number sent to JustCall dialer. Start the call inside the dialer if it does not auto-start.');
            }).catch(function (error) {
                callButton.disabled = false;
                enableFallback();
                setStatus('Could not start call: ' + (error && error.message ? error.message : 'Unknown error'));
                logSdkEvent({ status: 'failed', direction: 'outbound' });
            });
        });
    }
}
