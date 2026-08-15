// src/dialer/justcall-dialer-event-listener.ts
var JustCallDialerEventListeners = class {
  justcallClientEventEmitter;
  onLogin = null;
  onLogout = null;
  awaitedListeners = /* @__PURE__ */ new Map();
  constructor({
    onLogin,
    onLogout,
    clientEventEmitter
  }) {
    if (onLogin) this.onLogin = onLogin;
    if (onLogout) this.onLogout = onLogout;
    this.justcallClientEventEmitter = clientEventEmitter;
  }
  startListening() {
    window.addEventListener("message", this.handleMessage);
  }
  /* istanbul ignore next -- @preserve */
  awaitForEvent(event) {
    return new Promise((resolve, reject) => {
      this.awaitedListeners.set(event, { resolve, reject });
    });
  }
  handleMessage = (event) => {
    const { name: eventType, data: eventData } = event.data;
    const awiatedPromise = this.awaitedListeners.get(eventType);
    switch (eventType) {
      case "logged-in-status":
        this.justcallClientEventEmitter.handleLoggedIn(
          eventData,
          this.onLogin,
          this.onLogout
        );
        break;
      case "call-ringing":
        this.justcallClientEventEmitter.handleCallRinging(
          eventData
        );
        break;
      case "call-answered":
        this.justcallClientEventEmitter.handleCallAnswered(
          eventData
        );
        break;
      case "call-ended":
        this.justcallClientEventEmitter.handleCallEnded(
          eventData
        );
        break;
      case "sms-received":
        this.justcallClientEventEmitter.handleSMSReceived(
          eventData
        );
        break;
      case "is-logged-in": {
        {
          if (awiatedPromise) {
            const isLoggedInBool = eventData === "true";
            awiatedPromise.resolve(isLoggedInBool);
            this.awaitedListeners.delete(eventType);
          }
          break;
        }
      }
    }
  };
};

// src/utils/errors.ts
var JustcallDialerError = class _JustcallDialerError extends Error {
  constructor(errorCode, message) {
    super(`${errorCode}${message ? `: ${message}` : ""}`);
    this.errorCode = errorCode;
    Object.setPrototypeOf(this, _JustcallDialerError.prototype);
    this.name = "JustCallJustcallDialerError";
  }
};
var handleError = (errorCode) => {
  switch (errorCode) {
    case "no_dialer_id" /* no_dialer_id */:
      return new JustcallDialerError(
        "no_dialer_id" /* no_dialer_id */,
        "Dialer id is required to initiate justcall-dialer-sdk"
      );
    case "dialer_id_not_found" /* dialer_id_not_found */:
      return new JustcallDialerError(
        "dialer_id_not_found" /* dialer_id_not_found */
      );
    case "invalid_event_name" /* invalid_event_name */:
      return new JustcallDialerError(
        "invalid_event_name" /* invalid_event_name */
      );
    case "no_event_name" /* no_event_name */:
      return new JustcallDialerError("no_event_name" /* no_event_name */);
    case "not_subscribed_to_event" /* not_subscribed_to_event */:
      return new JustcallDialerError(
        "not_subscribed_to_event" /* not_subscribed_to_event */
      );
    case "dialer_not_ready" /* dialer_not_ready */:
      return new JustcallDialerError("dialer_not_ready" /* dialer_not_ready */);
    case "browser_environment_required" /* browser_environment_required */:
      return new JustcallDialerError(
        "browser_environment_required" /* browser_environment_required */
      );
    default:
      return new JustcallDialerError("unknown_error" /* unknown_error */);
  }
};

// src/dialer/justcall-client-event-emitter.ts
var JustCallClientEventEmitter = class {
  dialerEventListeners = /* @__PURE__ */ new Map();
  emit(event) {
    const listener = this.dialerEventListeners.get(event.name);
    if (listener) {
      listener(event.data);
    }
  }
  addDialerEventListener(event, callback) {
    this.dialerEventListeners.set(event, callback);
  }
  unsubscribeFromDialerEvent(event) {
    if (this.dialerEventListeners.has(event)) {
      this.dialerEventListeners.delete(event);
    } else throw handleError("not_subscribed_to_event" /* not_subscribed_to_event */);
  }
  unsubscribeAll() {
    this.dialerEventListeners.clear();
  }
  handleLoggedIn(data, onLogin, onLogout) {
    if (data.logged_in) {
      if (onLogin) onLogin(data);
    } else {
      if (onLogout) onLogout();
    }
  }
  handleCallRinging(data) {
    this.emit({ name: "call-ringing", data });
  }
  handleCallAnswered(data) {
    this.emit({ name: "call-answered", data });
  }
  handleCallEnded(data) {
    this.emit({ name: "call-ended", data });
  }
  handleSMSReceived(data) {
    this.emit({ name: "sms-received", data });
  }
};

// src/dialer/justcall-dialer-event-emitter.ts
var JustCallDialerEventEmitter = class {
  dialerIframe;
  constructor(iframe) {
    this.dialerIframe = iframe;
  }
  handleExternalDial(phoneNumber) {
    this.dialerIframe.contentWindow.postMessage(
      {
        type: "dial-number",
        phoneNumber
      },
      "https://app.justcall.io"
    );
  }
  handleIsLoggedIn() {
    this.dialerIframe.contentWindow.postMessage(
      {
        type: "is-logged-in"
      },
      "https://app.justcall.io"
    );
  }
};

// src/utils/contants.ts
var IFRAME_URL = "https://app.justcall.io/dialer";
var IFRAME_ALLOWED_PERMISSIONS = "microphone; autoplay; clipboard-read; clipboard-write;";
var validEmittableEvents = [
  "call-ringing",
  "call-answered",
  "call-ended",
  "sms-received"
];
var validEvents = [
  ...validEmittableEvents,
  "logged-in-status",
  "is-logged-in"
];

// src/template/dialer-iframe.ts
function getJustcallDialerIframe(document2) {
  const iframeElement = document2.createElement("iframe");
  iframeElement.src = IFRAME_URL;
  iframeElement.width = "365px";
  iframeElement.height = "610px";
  iframeElement.allow = IFRAME_ALLOWED_PERMISSIONS;
  iframeElement.style.border = "1px solid #e0e0e0";
  iframeElement.style.borderRadius = "10px";
  iframeElement.style.boxShadow = "0 2px 5px rgba(0, 0, 0, 0.1)";
  return iframeElement;
}

// src/dialer/index.ts
var JustCallDialer = class {
  dialerId;
  dialerDiv = null;
  dialerIframe = null;
  dialerEventListeners = null;
  clientEventEmitter;
  dialerEventEmitter = null;
  dialerReadyPromise;
  resolveDialerReadyPromise;
  rejectDialerReadyPromise;
  onLogin = null;
  onLogout = null;
  onReady = null;
  isDialerReady = false;
  constructor(props) {
    try {
      if (typeof window === "undefined") {
        throw handleError("browser_environment_required" /* browser_environment_required */);
      }
      const { onLogin = null, onLogout = null, dialerId, onReady = null } = props;
      this.onLogin = onLogin;
      this.onLogout = onLogout;
      this.dialerId = dialerId;
      this.onReady = onReady;
      this.dialerReadyPromise = new Promise((resolve, reject) => {
        this.resolveDialerReadyPromise = resolve;
        this.rejectDialerReadyPromise = reject;
      });
      this.clientEventEmitter = new JustCallClientEventEmitter();
      this.init();
    } catch (error) {
      {
        if (error instanceof JustcallDialerError) throw error;
        throw handleError("unknown_error" /* unknown_error */);
      }
    }
  }
  init() {
    if (!this.dialerId) {
      throw handleError("no_dialer_id" /* no_dialer_id */);
    }
    this.dialerDiv = document.getElementById(this.dialerId);
    if (!this.dialerDiv) {
      throw handleError("dialer_id_not_found" /* dialer_id_not_found */);
    }
    this.load();
  }
  load() {
    try {
      this.dialerIframe = getJustcallDialerIframe(document);
      this.dialerDiv?.appendChild(this.dialerIframe);
      this.dialerEventListeners = new JustCallDialerEventListeners({
        onLogin: this.onLogin,
        onLogout: this.onLogout,
        clientEventEmitter: this.clientEventEmitter
      });
      this.dialerEventEmitter = new JustCallDialerEventEmitter(
        this.dialerIframe
      );
      this.dialerEventListeners.startListening();
      this.dialerIframe.onload = () => {
        this.isDialerReady = true;
        if (this.onReady) this?.onReady();
        this.resolveDialerReadyPromise();
      };
      this.dialerIframe.onerror = () => {
        this.rejectDialerReadyPromise();
      };
    } catch (error) {
      {
        if (error instanceof JustcallDialerError) throw error;
        throw handleError("unknown_error" /* unknown_error */);
      }
    }
  }
  on(event, callback) {
    try {
      if (!event) {
        throw handleError("no_event_name" /* no_event_name */);
      }
      if (!validEmittableEvents.includes(event)) {
        throw handleError("invalid_event_name" /* invalid_event_name */);
      }
      this.clientEventEmitter.addDialerEventListener(event, callback);
    } catch (error) {
      {
        if (error instanceof JustcallDialerError) throw error;
        throw handleError("unknown_error" /* unknown_error */);
      }
    }
  }
  unsubscribe(event) {
    try {
      this.clientEventEmitter.unsubscribeFromDialerEvent(event);
    } catch (error) {
      {
        if (error instanceof JustcallDialerError) throw error;
        throw handleError("unknown_error" /* unknown_error */);
      }
    }
  }
  unsubscribeAll() {
    try {
      this.clientEventEmitter.unsubscribeAll();
    } catch (error) {
      {
        if (error instanceof JustcallDialerError) throw error;
        throw handleError("unknown_error" /* unknown_error */);
      }
    }
  }
  dialNumber(number) {
    try {
      if (!this.isDialerReady) {
        throw handleError("dialer_not_ready" /* dialer_not_ready */);
      }
      this.dialerEventEmitter.handleExternalDial(number);
    } catch (error) {
      {
        if (error instanceof JustcallDialerError) throw error;
        throw handleError("unknown_error" /* unknown_error */);
      }
    }
  }
  async isLoggedIn() {
    try {
      if (!this.isDialerReady) {
        throw handleError("dialer_not_ready" /* dialer_not_ready */);
      }
      {
        this.dialerEventEmitter?.handleIsLoggedIn();
        return await this.dialerEventListeners.awaitForEvent("is-logged-in");
      }
    } catch (error) {
      {
        if (error instanceof JustcallDialerError) throw error;
        throw handleError("unknown_error" /* unknown_error */);
      }
    }
  }
  /* istanbul ignore next -- @preserve */
  async ready() {
    return this.dialerReadyPromise;
  }
  destroy() {
    try {
      this.unsubscribeAll();
      if (this.dialerIframe && this.dialerIframe.parentNode) {
        this.dialerIframe.parentNode.removeChild(this.dialerIframe);
        this.dialerIframe = null;
      }
      this.dialerDiv = null;
      this.dialerEventListeners = null;
      this.dialerEventEmitter = null;
      this.isDialerReady = false;
      this.onLogin = null;
      this.onLogout = null;
      this.onReady = null;
    } catch (error) {
      {
        if (error instanceof JustcallDialerError) throw error;
        throw handleError("unknown_error" /* unknown_error */);
      }
    }
  }
};
export {
  JustCallDialer
};
/* istanbul ignore next -- @preserve */
/* istanbul ignore file -- @preserve */
/* istanbul ignore if -- @preserve */
