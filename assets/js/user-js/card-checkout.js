/**
 * card-checkout.js
 * ─────────────────────────────────────────────────────────────────────────────
 * Secure card payment via PayMongo Hosted Checkout.
 *
 * Card details are entered entirely on PayMongo's PCI-DSS certified page —
 * they NEVER touch your server.
 *
 * HOW TO USE
 * ──────────
 * 1. Drop this file in: assets/js/user-js/card-checkout.js
 * 2. Include it AFTER your existing booking JS on any page that needs it:
 *
 *      <script src="../../assets/js/user-js/card-checkout.js"></script>
 *
 * 3. When the user picks "Card" and clicks Confirm, call:
 *
 *      CardCheckout.start({
 *          bookingId : 42,          // required — integer
 *          roomName  : 'Unit 3B',   // for the confirmation screen
 *          checkin   : '2025-07-01',
 *          checkout  : '2025-08-01',
 *          total     : 15000,
 *          csrfToken : window.psGetCsrfToken?.() || '',
 *
 *          // Callbacks — wire these to whatever your modal uses
 *          onWaiting  : () => { ... show "waiting for payment" UI ... },
 *          onSuccess  : (data) => { ... show success screen ... },
 *          onFailed   : (ref)  => { ... show failed screen ... },
 *          onExpired  : (ref)  => { ... show expired screen ... },
 *          onError    : (msg)  => { ... show error toast ... },
 *      });
 *
 * 4. The function opens PayMongo's card checkout page in a new tab.
 *    It polls every 5 s (up to 6 min) until paid / failed / expired.
 * ─────────────────────────────────────────────────────────────────────────────
 */

(function (global) {
    'use strict';

    // ── Config ────────────────────────────────────────────────────────────────
    var API_CREATE = '../../api/user/create_card_checkout.php';
    var API_STATUS = '../../api/user/check_card_checkout_status.php';
    var POLL_INTERVAL_MS = 5000;
    var MAX_POLLS = 72;   // 72 × 5 s = 6 minutes

    // ── Internal state ────────────────────────────────────────────────────────
    var _pollTimer  = null;
    var _payTab     = null;
    var _payUrl     = '';
    var _sessionId  = '';
    var _bookingId  = 0;
    var _callbacks  = {};

    // ── Helpers ───────────────────────────────────────────────────────────────
    function _stopPolling() {
        if (_pollTimer) { clearInterval(_pollTimer); _pollTimer = null; }
    }

    function _reopenTab() {
        if (_payTab && !_payTab.closed) {
            _payTab.focus();
        } else if (_payUrl) {
            _payTab = window.open(_payUrl, '_blank');
        }
    }

    function _startPolling() {
        var polls = 0;
        var ref   = 'BK-' + String(_bookingId).padStart(4, '0');

        _pollTimer = setInterval(function () {
            if (++polls > MAX_POLLS) {
                _stopPolling();
                (_callbacks.onExpired || function () {})(ref);
                return;
            }

            fetch(
                API_STATUS +
                '?booking_id=' + encodeURIComponent(_bookingId) +
                '&session_id=' + encodeURIComponent(_sessionId)
            )
                .then(function (r) { return r.json(); })
                .then(function (st) {
                    var ps = st.payment_status || '';
                    var bs = st.booking_status || '';

                    if (ps === 'paid' || bs === 'confirmed') {
                        _stopPolling();
                        (_callbacks.onSuccess || function () {})({
                            booking_id : _bookingId,
                            session_id : _sessionId,
                        });
                    } else if (ps === 'failed' || bs === 'cancelled') {
                        _stopPolling();
                        (_callbacks.onFailed || function () {})(ref);
                    } else if (ps === 'expired') {
                        _stopPolling();
                        (_callbacks.onExpired || function () {})(ref);
                    }
                    // else still pending → keep polling
                })
                .catch(function () { /* network hiccup — retry next tick */ });
        }, POLL_INTERVAL_MS);
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Start a hosted card checkout session.
     *
     * @param {Object} opts
     * @param {number}   opts.bookingId
     * @param {string}   [opts.csrfToken]
     * @param {Function} [opts.onWaiting]   called immediately after checkout tab opens
     * @param {Function} [opts.onSuccess]   called with { booking_id, session_id }
     * @param {Function} [opts.onFailed]    called with ref string
     * @param {Function} [opts.onExpired]   called with ref string
     * @param {Function} [opts.onError]     called with error message string
     */
    function start(opts) {
        opts = opts || {};
        _bookingId = opts.bookingId || 0;
        _callbacks = {
            onWaiting : opts.onWaiting  || function () {},
            onSuccess : opts.onSuccess  || function () {},
            onFailed  : opts.onFailed   || function () {},
            onExpired : opts.onExpired  || function () {},
            onError   : opts.onError    || function () {},
        };

        if (!_bookingId) {
            _callbacks.onError('Missing booking ID.');
            return;
        }

        _stopPolling();
        _sessionId = '';
        _payTab    = null;
        _payUrl    = '';

        var fd = new FormData();
        fd.append('booking_id', _bookingId);
        if (opts.csrfToken) fd.append('csrf_token', opts.csrfToken);

        fetch(API_CREATE, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success || !data.checkout_url) {
                    _callbacks.onError(data.message || 'Could not create card checkout.');
                    return;
                }

                _sessionId = data.session_id;
                _payUrl    = data.checkout_url;

                // Open PayMongo's hosted card page in a new tab
                _payTab = window.open(_payUrl, '_blank');

                // Notify caller to show "waiting" UI
                _callbacks.onWaiting();

                // Start polling
                _startPolling();
            })
            .catch(function () {
                _callbacks.onError('Could not reach payment service. Please try again.');
            });
    }

    /**
     * Re-focus or re-open the PayMongo checkout tab.
     * Wire this to a "Reopen payment page" button in your modal.
     */
    function reopenTab() {
        _reopenTab();
    }

    /**
     * Abort polling (e.g. when the modal is closed).
     */
    function cancel() {
        _stopPolling();
    }

    // ── Export ────────────────────────────────────────────────────────────────
    global.CardCheckout = { start: start, reopenTab: reopenTab, cancel: cancel };

})(window);