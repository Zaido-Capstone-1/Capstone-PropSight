/**
 * toast.js — PropSight Global Toast Notification System
 * -------------------------------------------------------
 * Usage:
 *   showToast('Saved successfully!')               → green (success)
 *   showToast('Something went wrong.', 'error')    → red   (error)
 *   showToast('Please fill all fields.', 'warning')→ amber (warning)
 *   showToast('Loading complete.', 'info')         → blue  (info)
 *
 * Toasts stack vertically, auto-dismiss after 4s,
 * and can be closed manually by clicking the × button.
 */

(function () {
    /* ── Inject styles once ─────────────────────────────── */
    const STYLE_ID = 'propsight-toast-style';
    if (!document.getElementById(STYLE_ID)) {
        const style = document.createElement('style');
        style.id = STYLE_ID;
        style.textContent = `
            #ps-toast-container {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 99999;
                display: flex;
                flex-direction: column;
                gap: 10px;
                pointer-events: none;
                max-width: 360px;
                width: calc(100vw - 40px);
            }

            .ps-toast {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 14px 16px;
                border-radius: 10px;
                box-shadow: 0 8px 24px rgba(0,0,0,0.18), 0 2px 8px rgba(0,0,0,0.10);
                pointer-events: all;
                cursor: default;
                opacity: 0;
                transform: translateX(110%);
                transition: opacity 0.3s ease, transform 0.35s cubic-bezier(0.34,1.56,0.64,1);
                min-width: 0;
                position: relative;
            }

            .ps-toast.ps-toast--show {
                opacity: 1;
                transform: translateX(0);
            }

            .ps-toast.ps-toast--hide {
                opacity: 0;
                transform: translateX(110%);
                transition: opacity 0.25s ease, transform 0.25s ease;
            }

            /* ── Variants ── */
            .ps-toast--success {
                background: #f0fdf4;
                border-color: #16a34a;
                color: #14532d;
            }
            .ps-toast--error {
                background: #fef2f2;
                border-color: #dc2626;
                color: #7f1d1d;
            }
            .ps-toast--warning {
                background: #fffbeb;
                border-color: #d97706;
                color: #78350f;
            }
            .ps-toast--info {
                background: #eff6ff;
                border-color: #2563eb;
                color: #1e3a8a;
            }

            .ps-toast__icon {
                flex-shrink: 0;
                width: 20px;
                height: 20px;
            }

            .ps-toast--success .ps-toast__icon { color: #16a34a; }
            .ps-toast--error   .ps-toast__icon { color: #dc2626; }
            .ps-toast--warning .ps-toast__icon { color: #d97706; }
            .ps-toast--info    .ps-toast__icon { color: #2563eb; }

            .ps-toast__body {
                flex: 1;
                min-width: 0;
            }

            .ps-toast__msg {
                font-size: 0.88rem;
                line-height: 1.45;
                word-break: break-word;
                margin: 0;
            }

            .ps-toast__close {
                flex-shrink: 0;
                background: none;
                border: none;
                padding: 0;
                cursor: pointer;
                opacity: 0.5;
                line-height: 1;
                color: inherit;
                transition: opacity 0.2s;
            }
            .ps-toast__close:hover { opacity: 1; }

            @media (max-width: 480px) {
                #ps-toast-container {
                    top: 12px;
                    right: 12px;
                    min-width: auto;
                }
            }
        `;
        document.head.appendChild(style);
    }

    /* ── Create container once ──────────────────────────── */
    function getContainer() {
        let c = document.getElementById('ps-toast-container');
        if (!c) {
            c = document.createElement('div');
            c.id = 'ps-toast-container';
            document.body.appendChild(c);
        }
        return c;
    }

    /* ── SVG icons per type ─────────────────────────────── */
    const ICONS = {
        success: `<svg class="ps-toast__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>`,
        error: `<svg class="ps-toast__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>`,
        warning: `<svg class="ps-toast__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
        info: `<svg class="ps-toast__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`,
    };

    /* ── Core showToast function ────────────────────────── */
    /**
     * @param {string} message   - The message to show.
     * @param {string} [type]    - 'success' | 'error' | 'warning' | 'info'  (default: 'success')
     * @param {number} [duration] - Milliseconds before auto-dismiss (default: 2000)
     */
    window.showToast = function (message, type, duration) {
        /* normalise legacy calls: showToast(msg, true/false) */
        if (type === true) type = 'error';
        if (type === false || !type) type = 'success';
        if (!['success', 'error', 'warning', 'info'].includes(type)) type = 'success';

        const ms = (typeof duration === 'number' && duration > 0) ? duration : 2000;

        const container = getContainer();

        const toast = document.createElement('div');
        toast.className = `ps-toast ps-toast--${type}`;

        toast.innerHTML = `
            
            <div class="ps-toast__body">
                <div class="ps-toast__msg">${message}</div>
            </div>
            <button class="ps-toast__close" aria-label="Dismiss">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        `;

        container.appendChild(toast);

        /* Trigger enter animation on next frame */
        requestAnimationFrame(() => {
            requestAnimationFrame(() => toast.classList.add('ps-toast--show'));
        });

        /* Auto-dismiss */
        const timer = setTimeout(() => dismissToast(toast), ms);

        /* Manual close */
        toast.querySelector('.ps-toast__close').addEventListener('click', () => {
            clearTimeout(timer);
            dismissToast(toast);
        });
    };

    function dismissToast(toast) {
        toast.classList.remove('ps-toast--show');
        toast.classList.add('ps-toast--hide');
        toast.addEventListener('transitionend', () => toast.remove(), { once: true });
    }

    /* ── Convenience aliases ────────────────────────────── */
    window.showToastSuccess = (msg, dur) => window.showToast(msg, 'success', dur);
    window.showToastError = (msg, dur) => window.showToast(msg, 'error', dur);
    window.showToastWarning = (msg, dur) => window.showToast(msg, 'warning', dur);
    window.showToastInfo = (msg, dur) => window.showToast(msg, 'info', dur);

})();