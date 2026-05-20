let pendingReward = null;
let currentPoints = window.__PS_LOYALTY__.currentPoints;
let loyaltyRefreshTimer = null;

const tierDefs = [
    { name: 'Silver',   min: 0,    max: 499,  svgClass: 'silver'   },
    { name: 'Gold',     min: 500,  max: 1999, svgClass: 'gold'     },
    { name: 'Platinum', min: 2000, max: 4999, svgClass: 'platinum' },
    { name: 'Diamond',  min: 5000, max: null, svgClass: 'diamond'  },
];

/* Inline SVGs for each tier (no emoji) */
const tierSVGs = {
    Silver:   `<svg class="tier-svg silver"   viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke-width="1.5"/><path d="M12 7v5l3 3" stroke-width="1.8" stroke-linecap="round"/><circle cx="12" cy="12" r="3" fill="currentColor" opacity=".25"/></svg>`,
    Gold:     `<svg class="tier-svg gold"     viewBox="0 0 24 24" fill="none"><polygon points="12,2 15.5,8.5 23,9.5 17.5,14.8 19,22 12,18.3 5,22 6.5,14.8 1,9.5 8.5,8.5" stroke-width="1.5" stroke-linejoin="round"/></svg>`,
    Platinum: `<svg class="tier-svg platinum" viewBox="0 0 24 24" fill="none"><path d="M12 2L6 7H2l2 5-2 5h4l6 5 6-5h4l-2-5 2-5h-4L12 2z" stroke-width="1.5" stroke-linejoin="round"/></svg>`,
    Diamond:  `<svg class="tier-svg diamond"  viewBox="0 0 24 24" fill="none"><path d="M2 9l3-6h14l3 6-10 12L2 9z" stroke-width="1.5" stroke-linejoin="round"/><path d="M2 9h20M8 3l-3 6 7 12M16 3l3 6-7 12" stroke-width="1.2" stroke-linecap="round"/></svg>`,
};

/* SVG icons matching each reward id */
const rewardSVGs = {
    1: `<svg viewBox="0 0 24 24" fill="none"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 01-1 1H4a1 1 0 01-1-1V9.5z" stroke-width="1.6" stroke-linejoin="round"/><path d="M9 21V12h6v9" stroke-width="1.6" stroke-linejoin="round"/></svg>`,
    2: `<svg viewBox="0 0 24 24" fill="none"><rect x="3" y="14" width="18" height="7" rx="1.5" stroke-width="1.6"/><rect x="5" y="9"  width="14" height="5"  rx="1"   stroke-width="1.6"/><path d="M12 9V3m-3 3l3-3 3 3" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
    3: `<svg viewBox="0 0 24 24" fill="none"><path d="M18 8h1a4 4 0 010 8h-1" stroke-width="1.6" stroke-linecap="round"/><path d="M2 8h16v9a4 4 0 01-4 4H6a4 4 0 01-4-4V8z" stroke-width="1.6" stroke-linejoin="round"/><line x1="6" y1="2" x2="6" y2="4" stroke-width="1.8" stroke-linecap="round"/><line x1="10" y1="2" x2="10" y2="4" stroke-width="1.8" stroke-linecap="round"/><line x1="14" y1="2" x2="14" y2="4" stroke-width="1.8" stroke-linecap="round"/></svg>`,
    4: `<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke-width="1.6"/><polyline points="12,7 12,12 16,14" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
    5: `<svg viewBox="0 0 24 24" fill="none"><path d="M12 22V12" stroke-width="1.6" stroke-linecap="round"/><path d="M12 12C12 12 7 10 5 5c3 0 7 2 7 7z" stroke-width="1.5" stroke-linejoin="round"/><path d="M12 12C12 12 17 10 19 5c-3 0-7 2-7 7z" stroke-width="1.5" stroke-linejoin="round"/><path d="M5 19c2-1 4-2 7-2s5 1 7 2" stroke-width="1.5" stroke-linecap="round"/></svg>`,
    6: `<svg viewBox="0 0 24 24" fill="none"><rect x="2" y="7" width="20" height="12" rx="2" stroke-width="1.6"/><path d="M7 19v2M17 19v2" stroke-width="1.8" stroke-linecap="round"/><circle cx="7" cy="14" r="1.5" fill="currentColor" opacity=".7"/><circle cx="17" cy="14" r="1.5" fill="currentColor" opacity=".7"/><path d="M2 11h20" stroke-width="1.4"/><path d="M12 7V5M8 5h8" stroke-width="1.5" stroke-linecap="round"/></svg>`,
};

/* ── Modal helpers ── */
function openModal(id) {
    document.getElementById(id).classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
    document.body.style.overflow = '';
}

/* ── Open redeem-confirm modal ── */
function redeemReward(btn) {
    const id     = parseInt(btn.dataset.id, 10);
    const name   = btn.dataset.name;
    const pts    = parseInt(btn.dataset.pts, 10);
    const desc   = btn.dataset.desc;

    pendingReward = { id, name, pts, desc };

    /* Put the matching SVG into the modal icon slot */
    const iconEl = document.getElementById('redeemIcon');
    if (iconEl) iconEl.innerHTML = rewardSVGs[id] || rewardSVGs[1];

    document.getElementById('redeemName').textContent      = name;
    document.getElementById('redeemDesc').textContent      = desc;
    document.getElementById('redeemCost').textContent      = pts.toLocaleString() + ' pts';
    document.getElementById('redeemRemaining').textContent = (currentPoints - pts).toLocaleString() + ' pts';

    const confirmBtn = document.getElementById('redeemConfirmBtn');
    confirmBtn.disabled  = false;
    confirmBtn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" style="width:15px;height:15px;stroke:currentColor;stroke-width:2.5;"><polyline points="20 6 9 17 4 12"/></svg> Redeem Now`;

    openModal('redeemModal');
}

/* ── Copy voucher ── */
function copyVoucher() {
    const code = document.getElementById('voucherCode').textContent;
    navigator.clipboard.writeText(code).then(() => {
        const btn = document.getElementById('voucherCopyBtn');
        btn.textContent = '✓ Copied!';
        setTimeout(() => {
            btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" style="width:13px;height:13px;stroke:currentColor;stroke-width:2;"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg> Copy`;
        }, 2000);
    }).catch(() => {
        if (typeof showToast === 'function') showToast('Could not copy automatically.', 'error');
    });
}

/* ── Confirm and POST redemption ── */
function confirmRedeem() {
    if (!pendingReward) return;
    const btn = document.getElementById('redeemConfirmBtn');
    btn.disabled = true;
    btn.textContent = 'Processing…';

    const fd = new FormData();
    fd.append('action',      'redeem');
    fd.append('reward_id',   pendingReward.id);
    fd.append('reward_name', pendingReward.name);
    fd.append('points',      pendingReward.pts);
    if (typeof window.psAppendCsrf === 'function') window.psAppendCsrf(fd);

    fetch('../../api/user/redeem.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                if (typeof showToast === 'function') showToast(data.message || 'Error occurred. Please try again.', 'error');
                btn.disabled   = false;
                btn.innerHTML  = `<svg viewBox="0 0 24 24" fill="none" style="width:15px;height:15px;stroke:currentColor;stroke-width:2.5;"><polyline points="20 6 9 17 4 12"/></svg> Redeem Now`;
                return;
            }

            closeModal('redeemModal');

            /* Show success modal */
            const voucher = data.voucher_code || generateVoucherCode(pendingReward.id);
            document.getElementById('successRewardName').textContent = pendingReward.name;
            document.getElementById('successRewardDesc').textContent = pendingReward.desc;
            document.getElementById('voucherCode').textContent       = voucher;
            document.getElementById('successNewBalance').textContent = data.new_balance.toLocaleString() + ' pts';

            saveRedemptionLocally({
                reward_id:    pendingReward.id,
                reward_name:  pendingReward.name,
                points_used:  pendingReward.pts,
                voucher_code: voucher,
                redeemed_at:  new Date().toISOString(),
                new_balance:  data.new_balance,
            });

            openModal('redeemSuccessModal');

            btn.disabled  = false;
            btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" style="width:15px;height:15px;stroke:currentColor;stroke-width:2.5;"><polyline points="20 6 9 17 4 12"/></svg> Redeem Now`;

            refreshLoyaltyData();
        })
        .catch(() => {
            if (typeof showToast === 'function') showToast('Network error. Please try again.', 'error');
            btn.disabled  = false;
            btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" style="width:15px;height:15px;stroke:currentColor;stroke-width:2.5;"><polyline points="20 6 9 17 4 12"/></svg> Redeem Now`;
        });
}

/* ── Fallback voucher code ── */
function generateVoucherCode(rewardId) {
    const ts = Date.now().toString(36).toUpperCase();
    return `PS-R${String(rewardId).padStart(2,'0')}-${ts}`;
}

/* ── Local redemption log ── */
function saveRedemptionLocally(entry) {
    try {
        const key  = 'ps_redemptions';
        const prev = JSON.parse(sessionStorage.getItem(key) || '[]');
        prev.unshift(entry);
        sessionStorage.setItem(key, JSON.stringify(prev.slice(0, 50)));
    } catch (_) {}
}

/* ── Tier helper ── */
function getTierMeta(points) {
    let current = tierDefs[0];
    for (const t of tierDefs) { if (points >= t.min) current = t; }
    const idx       = tierDefs.findIndex(t => t.name === current.name);
    const next      = tierDefs[idx + 1] || null;
    const ptsToNext = next ? Math.max(0, next.min - points) : 0;
    const tierTotal = next ? next.min : points + 1000;
    const tierBase  = current.min;
    const progress  = next ? Math.min(100, Math.round(((points - tierBase) / Math.max(1, tierTotal - tierBase)) * 100)) : 100;
    return { current, next, ptsToNext, tierTotal, progress };
}

/* ── Render history ── */
const escHtml = v => String(v ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');

function renderHistory(items) {
    const wrap = document.getElementById('loyaltyHistoryList');
    if (!wrap) return;
    const list = Array.isArray(items) ? items : [];
    if (!list.length) {
        wrap.innerHTML = `<div class="history-empty"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke-width="1.5"/><path d="M12 8v4l3 3" stroke-width="1.8" stroke-linecap="round"/></svg><p>No activity yet. Start booking to earn points!</p></div>`;
        return;
    }
    wrap.innerHTML = list.slice(0, 30).map(h => {
        const t        = String(h.type || 'bonus').toLowerCase();
        const safeType = ['earn','redeem','bonus'].includes(t) ? t : 'bonus';
        const pts      = Number(h.points || 0);
        const ptsText  = `${pts >= 0 ? '+' : ''}${pts.toLocaleString()} pts`;
        const dt       = h.created_at ? new Date(h.created_at) : null;
        const dateText = dt && !isNaN(dt) ? dt.toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' }) : '';

        let iconSvg = `<svg viewBox="0 0 24 24" fill="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" stroke-width="1.6" stroke-linejoin="round"/></svg>`;
        if (t === 'earn')   iconSvg = `<svg viewBox="0 0 24 24" fill="none"><line x1="12" y1="19" x2="12" y2="5" stroke-width="2"/><polyline points="5 12 12 5 19 12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
        if (t === 'redeem') iconSvg = `<svg viewBox="0 0 24 24" fill="none"><path d="M20 12V22H4V12"/><path d="M22 7H2v5h20V7z" stroke-linejoin="round"/><path d="M12 22V7"/></svg>`;

        return `<div class="history-item">
            <div class="h-dot ${safeType}">${iconSvg}</div>
            <div class="h-desc">
                <div class="h-desc-main">${escHtml(h.description || 'Loyalty update')}</div>
                <div class="h-desc-date">${escHtml(dateText)}</div>
            </div>
            <div class="h-pts ${safeType}">${ptsText}</div>
        </div>`;
    }).join('');
}

/* ── Update reward cards ── */
function renderRewards(balance) {
    const balEl = document.getElementById('rewardsBalanceDisplay');
    if (balEl) balEl.textContent = balance.toLocaleString() + ' pts';

    document.querySelectorAll('#rewardsGrid .reward-card').forEach(card => {
        const btn    = card.querySelector('.btn-redeem');
        const cost   = parseInt(card.dataset.rewardPts || '0', 10);
        const lockEl = card.querySelector('.reward-lock-icon');
        const iconEl = card.querySelector('.reward-svg-icon');
        if (!btn) return;
        const can = balance >= cost;
        btn.disabled = !can;
        btn.innerHTML = can
            ? `<svg viewBox="0 0 24 24" fill="none" style="width:12px;height:12px;stroke:currentColor;stroke-width:2.5;"><polyline points="20 6 9 17 4 12"/></svg> Redeem`
            : `<svg viewBox="0 0 24 24" fill="none" style="width:12px;height:12px;stroke:currentColor;stroke-width:2;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Need more`;
        if (lockEl) lockEl.innerHTML = can ? '' : `<svg viewBox="0 0 24 24" fill="none"><rect x="5" y="11" width="14" height="10" rx="2" stroke-width="1.6"/><path d="M8 11V7a4 4 0 018 0v4" stroke-width="1.6" stroke-linecap="round"/></svg>`;
        if (iconEl) { iconEl.className = `reward-svg-icon ${can ? 'unlocked' : 'locked'}`; }
        card.classList.toggle('reward-locked', !can);
    });
}

/* ── Render full loyalty state ── */
function renderLoyalty(payload) {
    const balance  = Number(payload.balance || 0);
    currentPoints  = Math.max(0, balance);
    const meta     = getTierMeta(currentPoints);
    const nextName = meta.next ? meta.next.name : 'Diamond';
    const tierName = meta.current.name;

    const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };

    set('loyaltyPointsNum',  currentPoints.toLocaleString());
    set('loyaltyTierName',   tierName);

    const subEl = document.getElementById('loyaltyPointsSub');
    if (subEl) {
        subEl.innerHTML = meta.ptsToNext > 0
            ? `<svg viewBox="0 0 24 24" fill="none" style="width:12px;height:12px;stroke:currentColor;stroke-width:2;"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg> ${meta.ptsToNext.toLocaleString()} pts to ${nextName}`
            : `<svg viewBox="0 0 24 24" fill="none" style="width:12px;height:12px;stroke:currentColor;stroke-width:2;"><polyline points="20 6 9 17 4 12"/></svg> Highest tier reached!`;
    }

    /* Update tier icon (SVG, no emoji) */
    const tierIconWrap = document.getElementById('loyaltyTierIcon');
    if (tierIconWrap) tierIconWrap.innerHTML = tierSVGs[tierName] || tierSVGs['Silver'];

    /* Update tier badge data attribute for CSS theming */
    const tierBadge = document.getElementById('loyaltyTierBadge');
    if (tierBadge) tierBadge.dataset.tier = tierName.toLowerCase();

    const fill = document.getElementById('progressFill');
    if (fill) fill.style.width = `${meta.progress}%`;

    const lLeft  = document.getElementById('loyaltyProgressLeft');
    const lRight = document.getElementById('loyaltyProgressRight');
    if (lLeft)  lLeft.innerHTML  = `<span class="prog-dot"></span> ${tierName} &nbsp;·&nbsp; ${currentPoints.toLocaleString()} pts`;
    if (lRight) lRight.innerHTML = `${nextName} &nbsp;·&nbsp; ${meta.tierTotal.toLocaleString()} pts <span class="prog-dot"></span>`;

    set('summaryBalance',  `${currentPoints.toLocaleString()} pts`);
    set('summaryNextTier', nextName);
    set('summaryPtsNeeded', meta.ptsToNext > 0 ? `${meta.ptsToNext.toLocaleString()} pts` : '—');
    set('summaryProgress', `${meta.progress}%`);

    /* Update sidebar tier with SVG */
    const summaryTierSvg = document.getElementById('summaryTierSvg');
    if (summaryTierSvg) summaryTierSvg.innerHTML = tierSVGs[tierName] || '';
    const summaryTierEl = document.getElementById('summaryTier');
    if (summaryTierEl) {
        summaryTierEl.innerHTML = `<span class="mini-tier-svg" id="summaryTierSvg">${tierSVGs[tierName] || ''}</span> ${tierName}`;
    }

    renderRewards(currentPoints);
    renderHistory(payload.history || []);
}

/* ── Fetch fresh data ── */
function refreshLoyaltyData() {
    fetch('../../api/user/loyalty.php')
        .then(r => r.json())
        .then(data => { if (data.success) renderLoyalty(data); })
        .catch(() => {});
}

/* ── Init ── */
setTimeout(() => {
    const fill = document.getElementById('progressFill');
    if (fill) fill.style.width = window.__PS_LOYALTY__.progressPct + '%';
}, 250);

['redeemModal','redeemSuccessModal'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('click', e => { if (e.target.id === id) closeModal(id); });
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeModal('redeemModal');
        closeModal('redeemSuccessModal');
        if (typeof closeSidebar === 'function') closeSidebar();
    }
});

window.addEventListener('ps:user_metrics', refreshLoyaltyData);
loyaltyRefreshTimer = setInterval(refreshLoyaltyData, 8000);
refreshLoyaltyData();

