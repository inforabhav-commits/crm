const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const elements = new Map();
const listeners = {};
const element = id => {
    if (!elements.has(id)) elements.set(id, {hidden: true, textContent: '', dataset: {}, addEventListener(type, fn) { this[type] = fn; }});
    return elements.get(id);
};
const context = vm.createContext({
    document: {getElementById: element, addEventListener(type, fn) { listeners[type] = fn; }},
    window: {addEventListener(type, fn) { listeners[type] = fn; }, setInterval() {}},
    Date, console,
});
vm.runInContext(fs.readFileSync('resources/js/app.js', 'utf8').replace("import './bootstrap';", ''), context);
listeners.DOMContentLoaded();
const incoming = {id: 4, record: {name: 'Customer'}, masked_number: 'XXXXXX3210', direction: 'inbound', status: 'ringing'};
listeners['crm-call-update']({detail: incoming});
assert.equal(element('justcall-dialer-panel').hidden, false);
assert.equal(element('crm-dialer-number').textContent, 'XXXXXX3210');
assert.equal(element('justcall-dialer-status').textContent, 'Ringing');
element('justcall-dialer-close').click();
listeners['crm-call-update']({detail: incoming});
assert.equal(element('justcall-dialer-panel').hidden, true, 'Polling must not reopen a minimized call');
listeners['crm-call-update']({detail: {...incoming, id: 5, status: 'completed', duration_seconds: 65}});
assert.equal(element('justcall-dialer-panel').hidden, false);
assert.equal(element('crm-dialer-duration').textContent, '01:05');
assert.equal(element('justcall-dialer-status').textContent, 'Ended');
listeners['crm-call-update']({detail: null});
assert.equal(element('justcall-dialer-status').textContent, 'No active call');
assert.equal(element('crm-private-dialer').hidden, true);
listeners['crm-call-update']({detail: {status: 'failed', direction: 'outbound', masked_number: 'XXXXXX3210', message: 'Secure calling is unavailable.'}});
assert.equal(element('crm-dialer-direction').textContent, 'Outgoing');
assert.equal(element('justcall-dialer-status').textContent, 'Secure calling is unavailable.');
console.log('PASS: masked rendering, minimize/poll, terminal duration, idle, blocked outbound (11 assertions).');
