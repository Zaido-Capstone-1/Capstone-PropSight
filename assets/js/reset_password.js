function togglePw(id, btn) {
    const inp = document.getElementById(id);
    const isText = inp.type === 'text';
    inp.type = isText ? 'password' : 'text';
    btn.querySelector('svg').innerHTML = isText
        ? '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'
        : '<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';
}

const pwInput = document.getElementById('rpPassword');
const confirmInput = document.getElementById('rpConfirm');
const strengthBar  = document.getElementById('strengthBar');
const strengthText = document.getElementById('strengthText');
const matchError   = document.getElementById('matchError');
const submitBtn    = document.getElementById('submitBtn');

if (pwInput) {
    pwInput.addEventListener('input', () => {
        const v = pwInput.value;
        let score = 0;
        if (v.length >= 8)  score++;
        if (v.length >= 12) score++;
        if (/[A-Z]/.test(v)) score++;
        if (/[0-9]/.test(v)) score++;
        if (/[^A-Za-z0-9]/.test(v)) score++;

        const labels = ['', 'Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
        const colors = ['', '#ef4444', '#f59e0b', '#3b82f6', '#10b981', '#059669'];
        const widths = ['0%', '20%', '40%', '60%', '80%', '100%'];

        strengthBar.style.width    = widths[score] || '0%';
        strengthBar.style.background = colors[score] || 'transparent';
        strengthText.textContent   = labels[score] || '';
        strengthText.style.color   = colors[score] || '#9ca3af';
    });

    confirmInput.addEventListener('input', () => {
        if (confirmInput.value && confirmInput.value !== pwInput.value) {
            matchError.style.display = 'block';
        } else {
            matchError.style.display = 'none';
        }
    });
}

const resetForm = document.getElementById('resetForm');
if (resetForm) {
    resetForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const alertError   = document.getElementById('alertError');
        const alertSuccess = document.getElementById('alertSuccess');
        alertError.style.display   = 'none';
        alertSuccess.style.display = 'none';

        const pw = pwInput.value;
        const cn = confirmInput.value;

        if (pw.length < 8) {
            alertError.textContent = 'Password must be at least 8 characters.';
            alertError.style.display = 'block';
            return;
        }
        if (pw !== cn) {
            alertError.textContent = 'Passwords do not match.';
            alertError.style.display = 'block';
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Resetting…';

        try {
            const fd = new FormData(resetForm);
            const res = await fetch('process/reset_password.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                resetForm.style.display = 'none';
                alertSuccess.innerHTML =
                    '<strong>Password reset!</strong> ' + data.message +
                    ' <a href="index.php" style="color:#15803d;font-weight:600;">Go to Login →</a>';
                alertSuccess.style.display = 'block';
            } else {
                alertError.textContent = data.message || 'Something went wrong. Please try again.';
                alertError.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.textContent = 'Reset Password';
            }
        } catch {
            alertError.textContent = 'Server error. Please try again.';
            alertError.style.display = 'block';
            submitBtn.disabled = false;
            submitBtn.textContent = 'Reset Password';
        }
    });
}

// Auto-open forgot password modal if redirected from index.php with ?forgot=1
// (handled by index.js — this page is standalone so no action needed here)
