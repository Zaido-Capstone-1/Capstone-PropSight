let countdownTimer = null;
const alertEl = document.getElementById('alert');
const sendBtn = document.getElementById('sendBtn');
const otpSection = document.getElementById('otpSection');
const otpInput = document.getElementById('otpInput');
const verifyBtn = document.getElementById('verifyBtn');
const resendBtn = document.getElementById('resendBtn');
const countdownEl = document.getElementById('countdown');

function showAlert(msg, type = 'error') {
    alertEl.className = 'alert ' + type;
    alertEl.textContent = msg;
    alertEl.style.display = 'block';
}

function clearAlert() {
    alertEl.style.display = 'none';
}

async function sendCode() {
    clearAlert();
    sendBtn.disabled = true;
    sendBtn.textContent = 'Sending…';
    try {
        const res = await fetch('process/send_verify_otp.php', {
            method: 'POST'
        });
        const data = await res.json();
        if (data.success) {
            showAlert(data.message, 'success');
            sendBtn.style.display = 'none';
            otpSection.classList.add('visible');
            otpInput.focus();
            startCountdown(60);
        } else {
            showAlert(data.message);
            sendBtn.disabled = false;
            sendBtn.textContent = 'Send Verification Code';
        }
    } catch {
        showAlert('Server error. Please try again.');
        sendBtn.disabled = false;
        sendBtn.textContent = 'Send Verification Code';
    }
}

async function resendCode() {
    resendBtn.style.display = 'none';
    clearAlert();
    try {
        const res = await fetch('process/send_verify_otp.php', {
            method: 'POST'
        });
        const data = await res.json();
        showAlert(data.message, data.success ? 'success' : 'error');
        if (data.success) startCountdown(60);
        else resendBtn.style.display = 'inline';
    } catch {
        showAlert('Could not resend. Please try again.');
        resendBtn.style.display = 'inline';
    }
}

async function submitOtp() {
    const code = otpInput.value.trim();
    if (!/^\d{6}$/.test(code)) {
        showAlert('Please enter the 6-digit code from your email.');
        return;
    }
    verifyBtn.disabled = true;
    verifyBtn.textContent = 'Verifying…';
    const fd = new FormData();
    fd.append('code', code);
    try {
        const res = await fetch('process/verify_otp.php', {
            method: 'POST',
            body: fd
        });
        const data = await res.json();
        if (data.success) {
            showAlert(data.message, 'success');
            setTimeout(() => {
                window.location.href = data.redirect;
            }, 1000);
        } else {
            showAlert(data.message);
            verifyBtn.disabled = false;
            verifyBtn.textContent = 'Verify & Continue';
        }
    } catch {
        showAlert('Server error. Please try again.');
        verifyBtn.disabled = false;
        verifyBtn.textContent = 'Verify & Continue';
    }
}

function startCountdown(seconds) {
    clearInterval(countdownTimer);
    resendBtn.style.display = 'none';
    let remaining = seconds;
    countdownEl.textContent = `Resend in ${remaining}s`;
    countdownTimer = setInterval(() => {
        remaining--;
        if (remaining <= 0) {
            clearInterval(countdownTimer);
            countdownEl.textContent = '';
            resendBtn.style.display = 'inline';
        } else {
            countdownEl.textContent = `Resend in ${remaining}s`;
        }
    }, 1000);
}

function initFromServerState() {
    const state = window.VERIFY_STATE || { codeAlreadySent: false, remainingCooldown: 0 };
    if (!state.codeAlreadySent) return; // brand-new session with no code sent yet, keep button

    // A code was already emailed (e.g. during registration) — skip straight
    // to the input instead of showing a "Send" button that will just fail.
    sendBtn.style.display = 'none';
    otpSection.classList.add('visible');
    otpInput.focus();

    if (state.remainingCooldown > 0) {
        startCountdown(state.remainingCooldown);
    } else {
        resendBtn.style.display = 'inline';
    }
}

initFromServerState();

otpInput.addEventListener('input', () => {
    otpInput.value = otpInput.value.replace(/\D/g, '');
    if (otpInput.value.length === 6) submitOtp();
});
otpInput.addEventListener('keydown', e => {
    if (e.key === 'Enter') submitOtp();
});