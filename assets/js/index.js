const loginModal = document.getElementById('loginModal');

function openModal(tab = 'login') {
    loginModal.classList.add('open');
    document.body.style.overflow = 'hidden';
    switchTab(tab);
}
function closeModal() {
    loginModal.classList.remove('open');
    document.body.style.overflow = '';
}
function switchTab(tab) {
    document.querySelectorAll('.modal-tab').forEach(t =>
        t.classList.toggle('active', t.dataset.tab === tab));
    document.querySelectorAll('.modal-form').forEach(f =>
        f.classList.toggle('active', f.id === 'tab-' + tab));
}

document.querySelectorAll('.toggle-password').forEach(btn => {
    btn.addEventListener('click', () => {
        const targetId = btn.getAttribute('data-target');
        const input = targetId ? document.getElementById(targetId) : null;
        if (!input) return;

        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        btn.setAttribute('title', show ? 'Hide password' : 'Show password');
    });
});

document.getElementById('modalClose').addEventListener('click', closeModal);
loginModal.addEventListener('click', e => { if (e.target === loginModal) closeModal(); });

document.querySelectorAll('.btn-login-header').forEach(btn => btn.addEventListener('click', () => openModal('login')));
document.querySelector('.btn-book-header')?.addEventListener('click', () => {
    document.querySelector('#cta')?.scrollIntoView({ behavior: 'smooth' });
});
document.querySelector('.btn-book-big')?.addEventListener('click', () => {
    openModal('login');
});

const hdr = document.getElementById('hdr');
window.addEventListener('scroll', () => hdr.classList.toggle('scrolled', scrollY > 20));

const burger = document.getElementById('hamburger');
const mob = document.getElementById('mobileNav');
let mobOpen = false;

burger.addEventListener('click', () => {
    mobOpen = !mobOpen;
    mob.classList.toggle('open', mobOpen);
    const s = burger.querySelectorAll('span');
    if (mobOpen) {
        s[0].style.transform = 'translateY(6.5px) rotate(45deg)';
        s[1].style.opacity = '0';
        s[2].style.transform = 'translateY(-6.5px) rotate(-45deg)';
    } else resetB();
});
function resetB() {
    burger.querySelectorAll('span').forEach(s => { s.style.transform = ''; s.style.opacity = ''; });
}
function closeMob() { mobOpen = false; mob.classList.remove('open'); resetB(); }

const slides = document.querySelectorAll('.carousel-slide');
const dots = document.querySelectorAll('.carousel-dots .dot');
const thumbs = document.querySelectorAll('.carousel-thumbs .thumb');
const slideWrap = document.getElementById('carouselSlides');
const labelName = document.getElementById('slideLabel');
const labelType = document.getElementById('slideType');

let current = 0;
let autoTimer = null;

function goTo(idx) {
    if (!slides.length) return;
    slides[current].classList.remove('active');
    dots[current].classList.remove('active');
    thumbs[current].classList.remove('active');
    current = (idx + slides.length) % slides.length;
    slides[current].classList.add('active');
    dots[current].classList.add('active');
    thumbs[current].classList.add('active');
    slideWrap.style.transform = `translateX(-${current * 100}%)`;
    const slide = slides[current];
    labelName.textContent = slide.dataset.label;
    labelType.textContent = slide.dataset.type;
    resetAuto();
}

function resetAuto() {
    clearInterval(autoTimer);
    autoTimer = setInterval(() => goTo(current + 1), 5000);
}

document.getElementById('nextBtn')?.addEventListener('click', () => goTo(current + 1));
document.getElementById('prevBtn')?.addEventListener('click', () => goTo(current - 1));
dots.forEach(d => d.addEventListener('click', () => goTo(+d.dataset.idx)));
thumbs.forEach(t => t.addEventListener('click', () => goTo(+t.dataset.idx)));

let touchStartX = 0;
const frame = document.getElementById('carouselFrame');
frame?.addEventListener('touchstart', e => { touchStartX = e.touches[0].clientX; }, { passive: true });
frame?.addEventListener('touchend', e => {
    const diff = touchStartX - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 40) goTo(diff > 0 ? current + 1 : current - 1);
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeModal();
        closePolicyModal();
        closeRoomPreview();
        return;
    }
    if (loginModal.classList.contains('open')) return;
    if (e.key === 'ArrowRight') goTo(current + 1);
    if (e.key === 'ArrowLeft') goTo(current - 1);
});

if (slides.length > 1) resetAuto();

const revealObs = new IntersectionObserver(entries => {
    entries.forEach(e => {
        if (e.isIntersecting) { e.target.classList.add('visible'); revealObs.unobserve(e.target); }
    });
}, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

document.querySelectorAll('.reveal').forEach(el => revealObs.observe(el));

/* ── Landing Room Preview Modal (view-only) ─────────────── */
const roomPreviewModal = document.getElementById('roomPreviewModal');
const roomPreviewClose = document.getElementById('roomPreviewClose');

function openRoomPreview(room) {
    if (!roomPreviewModal) return;
    document.getElementById('roomPreviewImage').src = room.image || 'assets/images/placeholder.jpg';
    document.getElementById('roomPreviewName').textContent = room.name || 'Room';
    document.getElementById('roomPreviewType').textContent = room.type || '';
    document.getElementById('roomPreviewLocation').textContent = room.location || '';
    document.getElementById('roomPreviewDesc').textContent = room.description || '';
    document.getElementById('roomPreviewPrice').textContent = (room.price || '—') + ' / night';

    const amenitiesWrap = document.getElementById('roomPreviewAmenities');
    const amenities = Array.isArray(room.amenities) ? room.amenities.filter(Boolean) : [];
    amenitiesWrap.innerHTML = amenities.length
        ? amenities.slice(0, 8).map(a => `<span class="room-preview-chip">${a}</span>`).join('')
        : '<span class="room-preview-chip">No amenities listed</span>';

    roomPreviewModal.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeRoomPreview() {
    if (!roomPreviewModal) return;
    roomPreviewModal.classList.remove('open');
    document.body.style.overflow = '';
}

function showAuthLoading(message) {
    let overlay = document.getElementById('ps-auth-loading');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'ps-auth-loading';
        overlay.style.cssText = 'position:fixed;inset:0;z-index:10000;display:none;align-items:center;justify-content:center;background:rgba(6,14,26,.55);backdrop-filter:blur(3px);padding:16px;';
        overlay.innerHTML = `
            <div style="background:#fff;border-radius:14px;padding:18px 20px;min-width:220px;display:flex;align-items:center;gap:12px;box-shadow:0 14px 40px rgba(6,14,26,.25);border:1px solid #e0eefa;">
                <div style="width:20px;height:20px;border:3px solid #bfdbf7;border-top-color:#2563a8;border-radius:50%;animation:psSpin .7s linear infinite;"></div>
                <div id="ps-auth-loading-text" style="font-size:.9rem;color:#112240;font-weight:600;">Please wait...</div>
            </div>`;
        document.body.appendChild(overlay);

        if (!document.getElementById('ps-auth-loading-style')) {
            const style = document.createElement('style');
            style.id = 'ps-auth-loading-style';
            style.textContent = '@keyframes psSpin{to{transform:rotate(360deg)}}';
            document.head.appendChild(style);
        }
    }

    const textEl = document.getElementById('ps-auth-loading-text');
    if (textEl) textEl.textContent = message || 'Please wait...';
    overlay.style.display = 'flex';
}

function hideAuthLoading() {
    const overlay = document.getElementById('ps-auth-loading');
    if (overlay) overlay.style.display = 'none';
}

document.querySelectorAll('.btn-room[data-room]').forEach(btn => {
    btn.addEventListener('click', () => {
        try {
            const room = JSON.parse(btn.dataset.room);
            openRoomPreview(room);
        } catch {
            showToast('Could not load room details.', 'error');
        }
    });
});

roomPreviewClose?.addEventListener('click', closeRoomPreview);
roomPreviewModal?.addEventListener('click', e => {
    if (e.target === roomPreviewModal) closeRoomPreview();
});

/* ── Footer policy modal ─────────────────────────────────── */
const policyModal = document.getElementById('policyModal');
const policyModalClose = document.getElementById('policyModalClose');
const policyModalTitle = document.getElementById('policyModalTitle');
const policyModalContent = document.getElementById('policyModalContent');

const policyContentMap = {
    privacy: {
        title: 'Privacy Policy',
        html: `
            <h4>Information We Collect</h4>
            <p>We collect your account details, contact information, booking preferences, and payment-related references required to process reservations.</p>
            <h4>How We Use Your Data</h4>
            <p>Your information is used to confirm bookings, send updates, provide support, and improve your experience on the platform.</p>
            <h4>Data Protection</h4>
            <p>We apply reasonable technical and organizational safeguards to protect your personal information from unauthorized access.</p>
            <h4>Your Rights</h4>
            <p>You may request access, correction, or deletion of your personal data by contacting support.</p>
            <h4>Retention</h4>
            <p>We keep essential booking and transaction records only as long as necessary for operations, compliance, and customer service.</p>
        `,
    },
    terms: {
        title: 'Terms and Conditions',
        html: `
            <h4>Use of Service</h4>
            <p>By using this site, you agree to provide accurate information and use the platform only for lawful booking purposes.</p>
            <h4>Account Responsibility</h4>
            <p>You are responsible for maintaining the confidentiality of your account credentials and for all activity under your account.</p>
            <h4>Pricing and Availability</h4>
            <p>Room rates, availability, and offers may change without prior notice. Confirmed bookings follow the details shown at checkout.</p>
            <h4>Policy Updates</h4>
            <p>We may revise these terms when needed. Continued use of the platform indicates acceptance of updated terms.</p>
            <h4>Prohibited Use</h4>
            <ul>
                <li>Submitting false identity or payment details.</li>
                <li>Attempting unauthorized access to accounts or systems.</li>
                <li>Using the platform for unlawful or abusive activity.</li>
            </ul>
        `,
    },
    booking: {
        title: 'Booking Policy',
        html: `
            <h4>Reservation Confirmation</h4>
            <p>Bookings are confirmed once payment is completed and a confirmation notice is issued to your registered email.</p>
            <h4>Check-In and Check-Out</h4>
            <p>Standard check-in and check-out schedules apply unless otherwise stated in your booking confirmation.</p>
            <h4>Cancellation and Changes</h4>
            <p>Cancellation eligibility and fees depend on the selected room and date. Modification requests are subject to availability.</p>
            <h4>No-Show</h4>
            <p>Failure to arrive without notice may result in cancellation of reservation and applicable charges.</p>
            <h4>Guest Responsibility</h4>
            <p>Guests are accountable for damages beyond normal wear and must comply with posted house rules during the stay.</p>
        `,
    },
};

function openPolicyModal(policyKey) {
    const data = policyContentMap[policyKey];
    if (!policyModal || !policyModalTitle || !policyModalContent || !data) return;
    policyModalTitle.textContent = data.title;
    policyModalContent.innerHTML = data.html;
    policyModal.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closePolicyModal() {
    if (!policyModal) return;
    policyModal.classList.remove('open');
    document.body.style.overflow = '';
}

document.querySelectorAll('.policy-link').forEach(link => {
    link.addEventListener('click', e => {
        e.preventDefault();
        openPolicyModal(link.dataset.policy);
    });
});

policyModalClose?.addEventListener('click', closePolicyModal);
policyModal?.addEventListener('click', e => {
    if (e.target === policyModal) closePolicyModal();
});

/* ── OTP Modal (native, no SweetAlert dependency) ───────── */
let otpCountdownInterval = null;
let otpDeadlineMs = 0;

function formatOtpTime(secondsLeft) {
    const safe = Math.max(0, secondsLeft);
    const mm = String(Math.floor(safe / 60)).padStart(2, '0');
    const ss = String(safe % 60).padStart(2, '0');
    return `${mm}:${ss}`;
}

function showOtpModal(attemptsRemaining, secondsRemaining, onSubmit, onCancel, onExpired) {
    let modal = document.getElementById('ps-otp-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'ps-otp-modal';
        modal.style.cssText = 'position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;background:rgba(6,14,26,0.62);backdrop-filter:blur(5px);padding:16px;';
        modal.innerHTML = `
                <div style="background:#fff;border-radius:20px;padding:36px 32px 28px;width:100%;max-width:400px;box-shadow:0 26px 54px rgba(6,14,26,0.35);border:1px solid #e0eefa;box-sizing:border-box;overflow:hidden;">                <div style="text-align:center;margin-bottom:20px;">
                    <div style="width:64px;height:64px;border-radius:50%;background:#e6f1fb;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#185fa5" stroke-width="1.8" style="width:28px;height:28px;"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/><circle cx="12" cy="16" r="1.2" fill="#185fa5" stroke="none"/></svg>
                    </div>
                    <h3 style="margin:0 0 6px;font-size:1.15rem;font-weight:700;color:#112240;">Two-Factor Verification</h3>
                    <p id="ps-otp-subtext" style="margin:0;font-size:0.85rem;color:#5a7185;line-height:1.5;"></p>
                </div>
                <div style="background:#f8fbff;border:1px solid #e0eefa;border-radius:12px;padding:10px 14px;margin-bottom:18px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:0.72rem;color:#5a7185;font-weight:600;letter-spacing:.06em;text-transform:uppercase;">Code expires in</span>
                        <strong id="ps-otp-timer" style="font-size:0.88rem;color:#112240;font-variant-numeric:tabular-nums;">05:00</strong>
                    </div>
                    <div style="height:4px;background:#e0eefa;border-radius:999px;overflow:hidden;margin-top:8px;">
                        <div id="ps-otp-timer-bar" style="height:100%;width:100%;background:#185fa5;border-radius:999px;transition:width .9s linear;"></div>
                    </div>
                </div>
                <div id="ps-otp-boxes" style="display:flex;gap:6px;margin-bottom:6px;width:100%;box-sizing:border-box;">
                    <input type="text" maxlength="1" inputmode="numeric" class="ps-otp-box" style="flex:1;min-width:0;height:54px;text-align:center;font-size:1.4rem;font-weight:600;border:1.5px solid #e0eefa;border-radius:10px;outline:none;font-variant-numeric:tabular-nums;color:#112240;"/>
                    <input type="text" maxlength="1" inputmode="numeric" class="ps-otp-box" style="flex:1;min-width:0;height:54px;text-align:center;font-size:1.4rem;font-weight:600;border:1.5px solid #e0eefa;border-radius:10px;outline:none;font-variant-numeric:tabular-nums;color:#112240;"/>
                    <input type="text" maxlength="1" inputmode="numeric" class="ps-otp-box" style="flex:1;min-width:0;height:54px;text-align:center;font-size:1.4rem;font-weight:600;border:1.5px solid #e0eefa;border-radius:10px;outline:none;font-variant-numeric:tabular-nums;color:#112240;"/>
                    <input type="text" maxlength="1" inputmode="numeric" class="ps-otp-box" style="flex:1;min-width:0;height:54px;text-align:center;font-size:1.4rem;font-weight:600;border:1.5px solid #e0eefa;border-radius:10px;outline:none;font-variant-numeric:tabular-nums;color:#112240;"/>
                    <input type="text" maxlength="1" inputmode="numeric" class="ps-otp-box" style="flex:1;min-width:0;height:54px;text-align:center;font-size:1.4rem;font-weight:600;border:1.5px solid #e0eefa;border-radius:10px;outline:none;font-variant-numeric:tabular-nums;color:#112240;"/>
                    <input type="text" maxlength="1" inputmode="numeric" class="ps-otp-box" style="flex:1;min-width:0;height:54px;text-align:center;font-size:1.4rem;font-weight:600;border:1.5px solid #e0eefa;border-radius:10px;outline:none;font-variant-numeric:tabular-nums;color:#112240;"/>
                </div>
                <p id="ps-otp-error" style="margin:0 0 14px;font-size:0.8rem;color:#dc2626;display:none;text-align:center;"></p>
                <button id="ps-otp-verify" style="width:100%;padding:13px;background:#112240;color:#e8c882;border:none;border-radius:10px;font-size:0.92rem;font-weight:600;cursor:pointer;margin-bottom:10px;">Verify OTP</button>
                <button id="ps-otp-cancel" style="width:100%;padding:11px;background:transparent;color:#5a7185;border:1.5px solid #e0eefa;border-radius:10px;font-size:0.88rem;cursor:pointer;">Cancel</button>
            </div>`;
        document.body.appendChild(modal);
    }

    // Reset state
    const boxes = modal.querySelectorAll('.ps-otp-box');
    const errEl = document.getElementById('ps-otp-error');
    const timerEl = document.getElementById('ps-otp-timer');
    const timerBar = document.getElementById('ps-otp-timer-bar');
    const verifyBtn = document.getElementById('ps-otp-verify');
    const cancelBtn = document.getElementById('ps-otp-cancel');
    const totalSeconds = Math.max(1, Number(secondsRemaining) || 300);

    document.getElementById('ps-otp-subtext').innerHTML =
        `OTP sent to your email. Attempts remaining: <strong style="color:#112240;">${attemptsRemaining}</strong>`;

    boxes.forEach(b => {
        b.value = '';
        b.disabled = false;
        b.style.borderColor = '#e0eefa';
        b.style.boxShadow = '';
    });
    errEl.style.display = 'none';
    verifyBtn.disabled = false;
    verifyBtn.style.opacity = '1';
    verifyBtn.textContent = 'Verify OTP';
    timerBar.style.background = '#185fa5';
    modal.style.display = 'flex';
    setTimeout(() => boxes[0].focus(), 50);

    // Wire up box interactions
    boxes.forEach((box, i) => {
        box.oninput = e => {
            const val = e.target.value.replace(/\D/g, '');
            e.target.value = val;
            e.target.style.borderColor = val ? '#185fa5' : '#e0eefa';
            e.target.style.boxShadow = '';
            if (val && i < boxes.length - 1) boxes[i + 1].focus();
        };
        box.onkeydown = e => {
            if (e.key === 'Backspace' && !box.value && i > 0) boxes[i - 1].focus();
            if (e.key === 'Enter') doVerify();
        };
        box.onfocus = () => {
            box.style.borderColor = '#185fa5';
            box.style.boxShadow = '0 0 0 3px rgba(24,95,165,0.12)';
        };
        box.onblur = () => {
            box.style.boxShadow = '';
            if (!box.value) box.style.borderColor = '#e0eefa';
        };
        box.onpaste = e => {
            e.preventDefault();
            const pasted = (e.clipboardData || window.clipboardData)
                .getData('text')
                .replace(/\D/g, '')
                .slice(0, 6);

            if (!pasted) return;

            const allBoxes = Array.from(modal.querySelectorAll('.ps-otp-box'));
            pasted.split('').forEach((char, j) => {
                if (allBoxes[i + j]) {
                    allBoxes[i + j].value = char;
                    allBoxes[i + j].style.borderColor = '#185fa5';
                    allBoxes[i + j].style.boxShadow = '';
                }
            });

            // Focus last filled box or next empty
            const lastFilled = Math.min(i + pasted.length, allBoxes.length - 1);
            allBoxes[lastFilled].focus();
        };
    });

    // Timer
    const updateTimer = () => {
        const remaining = Math.max(0, Math.floor((otpDeadlineMs - Date.now()) / 1000));
        timerEl.textContent = formatOtpTime(remaining);
        timerBar.style.width = `${Math.max(0, (remaining / totalSeconds) * 100)}%`;
        if (remaining <= 60) timerBar.style.background = '#a32d2d';
        if (remaining <= 0) {
            clearInterval(otpCountdownInterval);
            otpCountdownInterval = null;
            verifyBtn.disabled = true;
            verifyBtn.style.opacity = '.55';
            boxes.forEach(b => b.disabled = true);
            errEl.textContent = 'OTP expired. Please log in again to request a new code.';
            errEl.style.display = 'block';
            if (onExpired) onExpired();
        }
    };

    clearInterval(otpCountdownInterval);
    updateTimer();
    otpCountdownInterval = setInterval(updateTimer, 1000);

    // Verify logic
    const doVerify = () => {
        if (Date.now() >= otpDeadlineMs) {
            errEl.textContent = 'OTP expired. Please log in again to request a new code.';
            errEl.style.display = 'block';
            verifyBtn.disabled = true;
            boxes.forEach(b => b.disabled = true);
            return;
        }
        const val = Array.from(boxes).map(b => b.value).join('');
        if (val.length < 6) {
            errEl.textContent = 'Please enter all 6 digits.';
            errEl.style.display = 'block';
            const firstEmpty = Array.from(boxes).findIndex(b => !b.value);
            if (firstEmpty !== -1) boxes[firstEmpty].focus();
            return;
        }
        clearInterval(otpCountdownInterval);
        otpCountdownInterval = null;
        modal.style.display = 'none';
        onSubmit(val);
    };

    verifyBtn.onclick = doVerify;
    cancelBtn.onclick = () => {
        clearInterval(otpCountdownInterval);
        otpCountdownInterval = null;
        modal.style.display = 'none';
        if (onCancel) onCancel();
    };
}

function hideOtpModal() {
    const m = document.getElementById('ps-otp-modal');
    if (m) m.style.display = 'none';
    clearInterval(otpCountdownInterval);
    otpCountdownInterval = null;
}

async function verifyOtp(attempts = 0, expiresInSeconds = 300) {
    const maxAttempts = 3;

    if (attempts >= maxAttempts) {
        otpDeadlineMs = 0;
        hideOtpModal();
        showToast('Too many incorrect attempts. Please log in again.', 'error');
        return;
    }

    if (!otpDeadlineMs) {
        otpDeadlineMs = Date.now() + (Math.max(1, Number(expiresInSeconds) || 300) * 1000);
    }
    const secondsLeft = Math.max(0, Math.floor((otpDeadlineMs - Date.now()) / 1000));
    if (secondsLeft <= 0) {
        otpDeadlineMs = 0;
        hideOtpModal();
        showToast('OTP expired. Please log in again to request a new code.', 'error');
        return;
    }

    showOtpModal(maxAttempts - attempts, secondsLeft, async (otp) => {
        try {
            showAuthLoading('Verifying OTP...');
            const res = await fetch('process/verify_login_otp.php', {
                method: 'POST',
                body: new URLSearchParams({ otp })
            });
            const data = await res.json();
            hideAuthLoading();

            if (data.status === 'success') {
                otpDeadlineMs = 0;
                // showToast(data.message, 'success');
                setTimeout(() => {
                    window.location.href = data.role === 'admin'
                        ? 'pages/admin/index.php'
                        : 'pages/user/user-dashboard.php';
                }, 1200);
            } else {
                hideAuthLoading();
                shakeOtpBoxes();
                const errEl = document.getElementById('ps-otp-error');
                if (errEl) {
                    errEl.textContent = data.message || 'Incorrect code. Please try again.';
                    errEl.style.display = 'block';
                }
                verifyOtp(attempts + 1, expiresInSeconds);
            }
        } catch {
            hideAuthLoading();
            showToast('Server error. Please try again.', 'error');
        }
    }, null, () => {
        showToast('OTP expired. Please log in again to request a new code.', 'error');
    });
}

function shakeOtpBoxes() {
    const boxes = document.querySelectorAll('.ps-otp-box');
    const boxWrap = document.getElementById('ps-otp-boxes');

    // Red borders
    boxes.forEach(b => {
        b.style.borderColor = '#dc2626';
        b.style.boxShadow = '0 0 0 3px rgba(220,38,38,0.12)';
    });

    // Shake animation
    if (!document.getElementById('ps-otp-shake-style')) {
        const style = document.createElement('style');
        style.id = 'ps-otp-shake-style';
        style.textContent = `
            @keyframes ps-shake {
                0%,100% { transform: translateX(0); }
                15%      { transform: translateX(-6px); }
                30%      { transform: translateX(6px); }
                45%      { transform: translateX(-4px); }
                60%      { transform: translateX(4px); }
                75%      { transform: translateX(-2px); }
                90%      { transform: translateX(2px); }
            }
        `;
        document.head.appendChild(style);
    }
    boxWrap.style.animation = 'none';
    boxWrap.offsetHeight; // force reflow
    boxWrap.style.animation = 'ps-shake 0.4s ease';

    // Reset border after shake, keep red until user types
    boxes.forEach(b => {
        b.oninput = e => {
            const val = e.target.value.replace(/\D/g, '');
            e.target.value = val;
            // restore normal focus color when they start typing
            e.target.style.borderColor = val ? '#185fa5' : '#e0eefa';
            e.target.style.boxShadow = val ? '0 0 0 3px rgba(24,95,165,0.12)' : '';
            const i = Array.from(boxes).indexOf(b);
            if (val && i < boxes.length - 1) boxes[i + 1].focus();
        };
    });
};

loginForm.addEventListener('submit', async function (e) {
    e.preventDefault();

    const formData = new FormData(loginForm);
    formData.append('login', true);

    try {
        showAuthLoading('Logging in...');
        const response = await fetch('process/login.php', { method: 'POST', body: formData });
        const data = await response.json();
        hideAuthLoading();

        if (data.status === 'otp_sent') {
            showToast(data.message, 'success');
            otpDeadlineMs = 0;
            verifyOtp(0, Number(data.otp_expires_in_seconds) || 300);
        } else if (data.status === 'success') {
            showToast(data.message, 'success');
            setTimeout(() => {
                window.location.href = data.role === 'admin'
                    ? 'pages/admin/index.php'
                    : 'pages/user/user-dashboard.php';
            }, 1200);
        } else {
            showToast(data.message, 'error');
        }
    } catch {
        hideAuthLoading();
        showToast('Server error! Please try again later.', 'error');
    }
});

const registerForm = document.getElementById('registerForm');
const signupCountryCode = document.getElementById('signupCountryCode');
const signupPhoneNumber = document.getElementById('signupPhoneNumber');
const countryCodeHint = document.getElementById('countryCodeHint');
const signupPassword = document.getElementById('signupPassword');
const signupConfirmPassword = document.getElementById('signupConfirmPassword');
const signupConfirmPasswordAlert = document.getElementById('signupConfirmPasswordAlert');
const signupAgreeTerms = document.getElementById('signupAgreeTerms');
const signupConsentAlert = document.getElementById('signupConsentAlert');
const signupPasswordStrengthBar = document.getElementById('signupPasswordStrengthBar');
const signupPasswordStrengthText = document.getElementById('signupPasswordStrengthText');

const phoneGuides = {
    '+63': { placeholder: '9123456789', max: 10, hint: 'Philippines: enter 10 digits (e.g. 9123456789).' },
    '+1': { placeholder: '5551234567', max: 10, hint: 'US/Canada: enter 10 digits area + number.' },
    '+44': { placeholder: '7400123456', max: 10, hint: 'UK: enter number without leading 0.' },
    '+61': { placeholder: '412345678', max: 9, hint: 'Australia: enter mobile without leading 0.' },
    '+65': { placeholder: '81234567', max: 8, hint: 'Singapore: enter 8-digit number.' },
    '+81': { placeholder: '9012345678', max: 10, hint: 'Japan: enter number without leading 0.' },
    '+82': { placeholder: '1012345678', max: 10, hint: 'Korea: enter number without leading 0.' },
    '+91': { placeholder: '9876543210', max: 10, hint: 'India: enter 10-digit mobile number.' },
    '+971': { placeholder: '501234567', max: 9, hint: 'UAE: enter number without leading 0.' },
    '+966': { placeholder: '512345678', max: 9, hint: 'Saudi: enter number without leading 0.' },
    '+974': { placeholder: '33123456', max: 8, hint: 'Qatar: enter 8-digit number.' },
    '+973': { placeholder: '36001234', max: 8, hint: 'Bahrain: enter 8-digit number.' },
    '+965': { placeholder: '50012345', max: 8, hint: 'Kuwait: enter 8-digit number.' },
};

function applyPhoneGuide() {
    if (!signupCountryCode || !signupPhoneNumber) return;
    const cfg = phoneGuides[signupCountryCode.value] || { placeholder: 'Phone Number', max: 15, hint: 'Use your local number without leading 0.' };
    signupPhoneNumber.placeholder = cfg.placeholder;
    signupPhoneNumber.maxLength = cfg.max;
    if (countryCodeHint) countryCodeHint.textContent = cfg.hint;
    if (signupPhoneNumber.value.length > cfg.max) {
        signupPhoneNumber.value = signupPhoneNumber.value.slice(0, cfg.max);
    }
}

signupCountryCode?.addEventListener('change', applyPhoneGuide);
signupPhoneNumber?.addEventListener('input', () => {
    signupPhoneNumber.value = signupPhoneNumber.value.replace(/\D/g, '');
});
applyPhoneGuide();

function updateSignupPasswordStrength() {
    if (!signupPassword || !signupPasswordStrengthBar || !signupPasswordStrengthText) return;
    const val = signupPassword.value || '';
    let score = 0;
    if (val.length >= 6) score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
    if (/\d/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    let label = 'Weak';
    let width = '20%';
    let color = '#ef4444';

    if (score >= 4) {
        label = 'Strong';
        width = '100%';
        color = '#16a34a';
    } else if (score >= 2) {
        label = 'Medium';
        width = '60%';
        color = '#f59e0b';
    }

    if (val.length === 0) {
        label = 'Weak';
        width = '0';
        color = '#ef4444';
    }

    signupPasswordStrengthBar.style.width = width;
    signupPasswordStrengthBar.style.background = color;
    signupPasswordStrengthText.textContent = `Password strength: ${label}`;
}

signupPassword?.addEventListener('input', updateSignupPasswordStrength);
updateSignupPasswordStrength();

function validateSignupPasswordMatch(showWhenEmpty = false) {
    if (!signupPassword || !signupConfirmPassword || !signupConfirmPasswordAlert) return true;
    const pw = signupPassword.value || '';
    const cpw = signupConfirmPassword.value || '';

    if (!showWhenEmpty && cpw.length === 0) {
        signupConfirmPasswordAlert.style.display = 'none';
        signupConfirmPasswordAlert.textContent = '';
        return true;
    }

    if (pw !== cpw) {
        signupConfirmPasswordAlert.textContent = 'Passwords do not match.';
        signupConfirmPasswordAlert.style.display = 'block';
        return false;
    }

    signupConfirmPasswordAlert.style.display = 'none';
    signupConfirmPasswordAlert.textContent = '';
    return true;
}

registerForm.addEventListener('submit', async function (e) {
    e.preventDefault();

    if (!validateSignupPasswordMatch(true)) {
        signupConfirmPassword?.focus();
        return;
    }

    if (signupAgreeTerms && !signupAgreeTerms.checked) {
        if (signupConsentAlert) {
            signupConsentAlert.textContent = 'Please agree to the Terms, Privacy Policy, and Booking Policy.';
            signupConsentAlert.style.display = 'block';
        }
        signupAgreeTerms.focus();
        return;
    }
    if (signupConsentAlert) {
        signupConsentAlert.textContent = '';
        signupConsentAlert.style.display = 'none';
    }

    const formData = new FormData(registerForm);
    formData.append('register', true);
    const createBtn = registerForm.querySelector('button[type="submit"][name="register"]');
    const originalBtnText = createBtn ? createBtn.textContent : '';

    try {
        if (createBtn) {
            createBtn.disabled = true;
            createBtn.textContent = 'Creating Account...';
            createBtn.style.opacity = '0.75';
            createBtn.style.cursor = 'not-allowed';
        }
        showAuthLoading('Creating account...');
        const response = await fetch('process/register.php', { method: 'POST', body: formData });
        const data = await response.json();
        hideAuthLoading();

        if (data.status === 'success') {
            showToast(data.message, 'success');
            setTimeout(() => { switchTab('login'); registerForm.reset(); }, 1500);
        } else {
            showToast(data.message, 'error');
        }
    } catch {
        hideAuthLoading();
        showToast('Server error! Please try again later.', 'error');
    } finally {
        if (createBtn) {
            createBtn.disabled = false;
            createBtn.textContent = originalBtnText;
            createBtn.style.opacity = '';
            createBtn.style.cursor = '';
        }
    }
});

/* ── Mobile Review Swiper ─────────────────────── */
(function () {
    const wrap = document.getElementById('testiSwiper');
    const inner = document.getElementById('testiSwiperInner');
    const dotsEl = document.getElementById('testiDots');
    const prevBtn = document.getElementById('testiPrev');
    const nextBtn = document.getElementById('testiNext');
    if (!wrap || !inner) return;

    const cards = inner.querySelectorAll('.testi-card');
    const dots = dotsEl ? dotsEl.querySelectorAll('.testi-swiper-dot') : [];
    const total = cards.length;
    let cur = 0;

    function goTo(idx) {
        cur = (idx + total) % total;
        inner.style.transform = `translateX(-${cur * 100}%)`;
        dots.forEach((d, i) => d.classList.toggle('active', i === cur));
    }

    prevBtn && prevBtn.addEventListener('click', () => goTo(cur - 1));
    nextBtn && nextBtn.addEventListener('click', () => goTo(cur + 1));
    dots.forEach(d => d.addEventListener('click', () => goTo(+d.dataset.idx)));

    // Touch / swipe support
    let startX = 0;
    inner.addEventListener('touchstart', e => { startX = e.touches[0].clientX; }, { passive: true });
    inner.addEventListener('touchend', e => {
        const diff = startX - e.changedTouches[0].clientX;
        if (Math.abs(diff) > 40) goTo(diff > 0 ? cur + 1 : cur - 1);
    });

    // Auto-advance every 6 seconds
    let timer = setInterval(() => goTo(cur + 1), 6000);
    wrap.addEventListener('touchstart', () => clearInterval(timer), { passive: true });
})();

// Store the original form HTML once, before any submission can mutate it
const _forgotOriginalHTML = `
    <div class="modal-header">
        <h2 class="modal-title" style="font-size:1.35rem;">Forgot Password?</h2>
        <p class="modal-sub">Enter the email you signed up with and we'll send you a reset link.</p>
    </div>
    <div class="modal-field" style="margin-top:20px;">
        <label>Email Address</label>
        <input type="email" id="forgotEmail" placeholder="your@email.com" autocomplete="email">
    </div>
    <button class="modal-btn-primary" id="forgotSubmitBtn" onclick="submitForgotPassword()" style="width:100%;margin-top:4px;">
        Send Reset Link
    </button>
    <p style="text-align:center;margin-top:14px;font-size:13px;color:#6b7280;">
        Remembered it? <a href="#" onclick="closeForgotModal();openModal('login');return false;" style="color:#1e3a5f;font-weight:600;text-decoration: none;">Back to Login</a>
    </p>
`;

function openForgotModal() {
    const loginModal = document.getElementById('loginModal');
    if (loginModal) loginModal.classList.remove('open');

    const modal = document.getElementById('forgotModal');
    if (!modal) return;

    // Always restore the original form HTML so the success state is wiped
    const wrap = document.getElementById('forgotFormWrap');
    if (wrap) wrap.innerHTML = _forgotOriginalHTML;

    // Reset the alert
    const alert = document.getElementById('forgotAlert');
    if (alert) { alert.style.display = 'none'; alert.textContent = ''; }

    modal.classList.add('open');
    document.body.style.overflow = 'hidden';

    setTimeout(() => {
        const email = document.getElementById('forgotEmail');
        if (email) email.focus();
    }, 120);
}

function closeForgotModal() {
    const modal = document.getElementById('forgotModal');
    if (modal) modal.classList.remove('open');

    // Only reset overflow if no other modals are open
    const loginModal = document.getElementById('loginModal');
    if (!loginModal || !loginModal.classList.contains('open')) {
        document.body.style.overflow = '';
    }
}

async function submitForgotPassword() {
    const email = document.getElementById('forgotEmail');
    const alert = document.getElementById('forgotAlert');
    const btn = document.getElementById('forgotSubmitBtn');

    alert.style.display = 'none';

    if (!email.value.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
        alert.style.cssText = 'display:block;background:#fef2f2;color:#dc2626;border:1px solid #fecaca;padding:12px 14px;border-radius:8px;font-size:13px;';
        alert.textContent = 'Please enter a valid email address.';
        email.focus();
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Sending…';

    try {
        const fd = new FormData();
        fd.append('email', email.value.trim());
        const res = await fetch('process/forgot_password.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            // Show success and hide the form inputs
            document.getElementById('forgotFormWrap').innerHTML = `
                <div style="text-align:center;padding:12px 0 8px;">
                    <div style="width:64px;height:64px;border-radius:50%;background:rgba(34,197,94,0.1);
                                display:flex;align-items:center;justify-content:center;margin:0 auto 18px;">
                        <svg viewBox="0 0 24 24" style="width:30px;height:30px;stroke:#16a34a;fill:none;stroke-width:2.5;">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                    </div>
                    <div style="font-size:1.1rem;font-weight:700;color:#111827;margin-bottom:10px;">Check Your Email</div>
                    <p style="font-size:13px;color:#6b7280;line-height:1.7;margin-bottom:20px;">
                        ${data.message}
                    </p>
                    <button id="backToLoginBtn"
                            style="background:#1e3a5f;color:#fff;border:none;padding:11px 28px;
                                   border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;">
                        Back to Login
                    </button>
                </div>`;

            // Attach click handler to the back to login button
            const backBtn = document.getElementById('backToLoginBtn');
            if (backBtn) {
                backBtn.addEventListener('click', () => {
                    closeForgotModal();
                    // Small delay to ensure modal is closed before opening login
                    setTimeout(() => openModal('login'), 100);
                });
            }

            // Auto-close and redirect after 3 seconds if user doesn't click the button
            setTimeout(() => {
                const forgotModal = document.getElementById('forgotModal');
                if (forgotModal && forgotModal.classList.contains('open')) {
                    closeForgotModal();
                    setTimeout(() => openModal('login'), 100);
                }
            }, 3000);
        } else {
            alert.style.cssText = 'display:block;background:#fef2f2;color:#dc2626;border:1px solid #fecaca;padding:12px 14px;border-radius:8px;font-size:13px;';
            alert.textContent = data.message || 'Something went wrong. Please try again.';
            btn.disabled = false;
            btn.textContent = 'Send Reset Link';
        }
    } catch {
        alert.style.cssText = 'display:block;background:#fef2f2;color:#dc2626;border:1px solid #fecaca;padding:12px 14px;border-radius:8px;font-size:13px;';
        alert.textContent = 'Server error. Please try again.';
        btn.disabled = false;
        btn.textContent = 'Send Reset Link';
    }
}

// Close forgot modal on backdrop click
document.addEventListener('DOMContentLoaded', () => {
    const fm = document.getElementById('forgotModal');
    if (fm) fm.addEventListener('click', e => { if (e.target === fm) closeForgotModal(); });

    // Auto-open forgot modal if URL has ?forgot=1
    if (new URLSearchParams(window.location.search).get('forgot') === '1') {
        openForgotModal();
    }
});
