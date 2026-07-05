let pendingReward = null;
let currentPoints = window.__PS_LOYALTY__.currentPoints;
let loyaltyRefreshTimer = null;
let _allVouchers = [];

const tierDefs = [{
        name: 'Silver',
        min: 0,
        max: 499,
        svgClass: 'silver'
    },
    {
        name: 'Gold',
        min: 500,
        max: 1999,
        svgClass: 'gold'
    },
    {
        name: 'Platinum',
        min: 2000,
        max: 4999,
        svgClass: 'platinum'
    },
    {
        name: 'Diamond',
        min: 5000,
        max: null,
        svgClass: 'diamond'
    },
];

/* Inline SVGs for each tier (no emoji) */
const tierSVGs = {
    Silver: `<svg class="tier-svg silver" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="15" r="6" stroke-width="1.5"/><path d="M10 13c0-1 4-1 4 0s-4 1-4 2 4 1 4 0" stroke-width="1.4" stroke-linecap="round"/><path d="M9 3h6l-1.5 5h-3L9 3z" stroke-width="1.4" stroke-linejoin="round"/></svg>`,
    Gold: `<svg class="tier-svg gold"     viewBox="0 0 24 24" fill="none"><polygon points="12,2 15.5,8.5 23,9.5 17.5,14.8 19,22 12,18.3 5,22 6.5,14.8 1,9.5 8.5,8.5" stroke-width="1.5" stroke-linejoin="round"/></svg>`,
    Platinum: `<svg class="tier-svg platinum" viewBox="0 0 24 24" fill="none"><path d="M12 2L6 7H2l2 5-2 5h4l6 5 6-5h4l-2-5 2-5h-4L12 2z" stroke-width="1.5" stroke-linejoin="round"/></svg>`,
    Diamond: `<svg class="tier-svg diamond"  viewBox="0 0 24 24" fill="none"><path d="M2 9l3-6h14l3 6-10 12L2 9z" stroke-width="1.5" stroke-linejoin="round"/><path d="M2 9h20M8 3l-3 6 7 12M16 3l3 6-7 12" stroke-width="1.2" stroke-linecap="round"/></svg>`,
};

/* SVG icons matching each reward id */
const rewardSVGs = [
    `<svg viewBox="0 0 24 24" fill="none"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 01-1 1H4a1 1 0 01-1-1V9.5z" stroke-width="1.6" stroke-linejoin="round"/><path d="M9 21V12h6v9" stroke-width="1.6" stroke-linejoin="round"/></svg>`,
    `<svg viewBox="0 0 24 24" fill="none"><rect x="3" y="14" width="18" height="7" rx="1.5" stroke-width="1.6"/><rect x="5" y="9" width="14" height="5" rx="1" stroke-width="1.6"/><path d="M12 9V3m-3 3l3-3 3 3" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
    `<svg viewBox="0 0 24 24" fill="none"><path d="M18 8h1a4 4 0 010 8h-1" stroke-width="1.6" stroke-linecap="round"/><path d="M2 8h16v9a4 4 0 01-4 4H6a4 4 0 01-4-4V8z" stroke-width="1.6" stroke-linejoin="round"/><line x1="6" y1="2" x2="6" y2="4" stroke-width="1.8" stroke-linecap="round"/><line x1="10" y1="2" x2="10" y2="4" stroke-width="1.8" stroke-linecap="round"/><line x1="14" y1="2" x2="14" y2="4" stroke-width="1.8" stroke-linecap="round"/></svg>`,
    `<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke-width="1.6"/><polyline points="12,7 12,12 16,14" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
    `<svg viewBox="0 0 24 24" fill="none"><path d="M12 22V12" stroke-width="1.6" stroke-linecap="round"/><path d="M12 12C12 12 7 10 5 5c3 0 7 2 7 7z" stroke-width="1.5" stroke-linejoin="round"/><path d="M12 12C12 12 17 10 19 5c-3 0-7 2-7 7z" stroke-width="1.5" stroke-linejoin="round"/><path d="M5 19c2-1 4-2 7-2s5 1 7 2" stroke-width="1.5" stroke-linecap="round"/></svg>`,
    `<svg viewBox="0 0 24 24" fill="none"><rect x="2" y="7" width="20" height="12" rx="2" stroke-width="1.6"/><path d="M7 19v2M17 19v2" stroke-width="1.8" stroke-linecap="round"/><circle cx="7" cy="14" r="1.5" fill="currentColor" opacity=".7"/><circle cx="17" cy="14" r="1.5" fill="currentColor" opacity=".7"/><path d="M2 11h20" stroke-width="1.4"/><path d="M12 7V5M8 5h8" stroke-width="1.5" stroke-linecap="round"/></svg>`,
    `<svg viewBox="0 0 24 24" fill="none"><path d="M20 12V22H4V12"/><path d="M22 7H2v5h20V7z" stroke-linejoin="round"/><path d="M12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z"/></svg>`,
    `<svg viewBox="0 0 24 24" fill="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" stroke-width="1.6" stroke-linejoin="round"/></svg>`,
];

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
    const id = parseInt(btn.dataset.id, 10);
    const name = btn.dataset.name;
    const pts = parseInt(btn.dataset.pts, 10);
    const desc = btn.dataset.desc;

    pendingReward = {
        id,
        name,
        pts,
        desc
    };

    /* Put the matching SVG into the modal icon slot */
    const svgIndex = parseInt(btn.dataset.svgId ?? 0) % rewardSVGs.length;
    const iconEl = document.getElementById('redeemIcon');
    if (iconEl) iconEl.innerHTML = rewardSVGs[svgIndex];

    document.getElementById('redeemName').textContent = name;
    document.getElementById('redeemDesc').textContent = desc;
    document.getElementById('redeemCost').textContent = pts.toLocaleString() + ' pts';
    document.getElementById('redeemRemaining').textContent = (currentPoints - pts).toLocaleString() + ' pts';

    const confirmBtn = document.getElementById('redeemConfirmBtn');
    confirmBtn.disabled = false;
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
    fd.append('action', 'redeem');
    fd.append('reward_id', pendingReward.id);
    fd.append('reward_name', pendingReward.name);
    fd.append('points', pendingReward.pts);
    if (typeof window.psAppendCsrf === 'function') window.psAppendCsrf(fd);

    fetch('../../endpoints/user/redeem.php', {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                if (typeof showToast === 'function') showToast(data.message || 'Error occurred. Please try again.', 'error');
                btn.disabled = false;
                btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" style="width:15px;height:15px;stroke:currentColor;stroke-width:2.5;"><polyline points="20 6 9 17 4 12"/></svg> Redeem Now`;
                return;
            }

            closeModal('redeemModal');

            /* Show success modal */
            const voucher = data.voucher_code || generateVoucherCode(pendingReward.id);
            document.getElementById('successRewardName').textContent = pendingReward.name;
            document.getElementById('successRewardDesc').textContent = pendingReward.desc;
            document.getElementById('voucherCode').textContent = voucher;
            document.getElementById('successNewBalance').textContent = data.new_balance.toLocaleString() + ' pts';

            saveRedemptionLocally({
                reward_id: pendingReward.id,
                reward_name: pendingReward.name,
                points_used: pendingReward.pts,
                voucher_code: voucher,
                redeemed_at: new Date().toISOString(),
                new_balance: data.new_balance,
            });

            openModal('redeemSuccessModal');

            btn.disabled = false;
            btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" style="width:15px;height:15px;stroke:currentColor;stroke-width:2.5;"><polyline points="20 6 9 17 4 12"/></svg> Redeem Now`;

            refreshLoyaltyData();
        })
        .catch(() => {
            if (typeof showToast === 'function') showToast('Network error. Please try again.', 'error');
            btn.disabled = false;
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
        const key = 'ps_redemptions';
        const prev = JSON.parse(sessionStorage.getItem(key) || '[]');
        prev.unshift(entry);
        sessionStorage.setItem(key, JSON.stringify(prev.slice(0, 50)));
    } catch (_) {}
}

/* ── Tier helper ── */
function getTierMeta(points) {
    let current = tierDefs[0];
    for (const t of tierDefs) {
        if (points >= t.min) current = t;
    }
    const idx = tierDefs.findIndex(t => t.name === current.name);
    const next = tierDefs[idx + 1] || null;
    const ptsToNext = next ? Math.max(0, next.min - points) : 0;
    const tierTotal = next ? next.min : points + 1000;
    const tierBase = current.min;
    const progress = next ? Math.min(100, Math.round(((points - tierBase) / Math.max(1, tierTotal - tierBase)) * 100)) : 100;
    return {
        current,
        next,
        ptsToNext,
        tierTotal,
        progress
    };
}

/* ── Render history ── */
const escHtml = v => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\"/g, '&quot;').replace(/'/g, '&#39;');

/* ── Pagination state ── */
let _historyAll = [];
let _historyPage = 1;
const HISTORY_PER_PAGE = 8;

function _buildHistoryItem(h) {
    const t = String(h.type || 'bonus').toLowerCase();
    const safeType = ['earn', 'redeem', 'bonus'].includes(t) ? t : 'bonus';
    const pts = Number(h.points || 0);
    const ptsText = `${pts >= 0 ? '+' : ''}${pts.toLocaleString()} pts`;
    const dt = h.created_at ? new Date(h.created_at) : null;
    const dateText = dt && !isNaN(dt) ? dt.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    }) : '';

    let iconSvg = `<svg viewBox="0 0 24 24" fill="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" stroke-width="1.6" stroke-linejoin="round"/></svg>`;
    if (t === 'earn') iconSvg = `<svg viewBox="0 0 24 24" fill="none"><line x1="12" y1="19" x2="12" y2="5" stroke-width="2"/><polyline points="5 12 12 5 19 12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
    if (t === 'redeem') iconSvg = `<svg viewBox="0 0 24 24" fill="none"><path d="M20 12V22H4V12"/><path d="M22 7H2v5h20V7z" stroke-linejoin="round"/><path d="M12 22V7"/></svg>`;

    return `<div class="history-item">
        <div class="h-dot ${safeType}">${iconSvg}</div>
        <div class="h-desc">
            <div class="h-desc-main">${escHtml(h.description || 'Loyalty update')}</div>
            <div class="h-desc-date">${escHtml(dateText)}</div>
        </div>
        <div class="h-pts ${safeType}">${ptsText}</div>
    </div>`;
}

function _renderHistoryPage() {
    const wrap = document.getElementById('loyaltyHistoryList');
    if (!wrap) return;

    const totalPages = Math.ceil(_historyAll.length / HISTORY_PER_PAGE);
    _historyPage = Math.max(1, Math.min(_historyPage, totalPages || 1));

    const start = (_historyPage - 1) * HISTORY_PER_PAGE;
    const slice = _historyAll.slice(start, start + HISTORY_PER_PAGE);

    wrap.innerHTML = slice.map(_buildHistoryItem).join('');

    /* ── Pagination controls ── */
    const pag = document.getElementById('historyPagination');
    const prevBtn = document.getElementById('prevPageBtn');
    const nextBtn = document.getElementById('nextPageBtn');
    const numWrap = document.getElementById('pageNumbers');

    if (!pag) return;

    pag.style.display = totalPages > 1 ? 'flex' : 'none';

    if (prevBtn) prevBtn.disabled = _historyPage <= 1;
    if (nextBtn) nextBtn.disabled = _historyPage >= totalPages;

    if (numWrap) {
        let html = '';
        const delta = 1; // pages around current
        for (let i = 1; i <= totalPages; i++) {
            if (
                i === 1 ||
                i === totalPages ||
                (i >= _historyPage - delta && i <= _historyPage + delta)
            ) {
                html += `<button class="page-btn${i === _historyPage ? ' active' : ''}" onclick="goToPage(${i})">${i}</button>`;
            } else if (
                i === _historyPage - delta - 1 ||
                i === _historyPage + delta + 1
            ) {
                html += `<span class="page-dots">…</span>`;
            }
        }
        numWrap.innerHTML = html;
    }
}

function goToPage(n) {
    _historyPage = n;
    _renderHistoryPage();
}

function changePage(dir) {
    if (dir === 'prev') _historyPage--;
    if (dir === 'next') _historyPage++;
    _renderHistoryPage();
}

function renderHistory(items) {
    const wrap = document.getElementById('loyaltyHistoryList');
    if (!wrap) return;

    _historyAll = Array.isArray(items) ? items : [];
    _historyPage = 1;

    if (!_historyAll.length) {
        wrap.innerHTML = `<div class="history-empty"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke-width="1.5"/><path d="M12 8v4l3 3" stroke-width="1.8" stroke-linecap="round"/></svg><p>No activity yet. Start booking to earn points!</p></div>`;
        const pag = document.getElementById('historyPagination');
        if (pag) pag.style.display = 'none';
        return;
    }

    _renderHistoryPage();
}

/* ── Update reward cards ── */
function renderRewards(balance, history = [], vouchers = []) {
    _allVouchers = vouchers;
    const balEl = document.getElementById('rewardsBalanceDisplay');
    if (balEl) balEl.textContent = balance.toLocaleString() + ' pts';

    // Count how many times each reward was redeemed
    const redeemCounts = {};
    (history || []).forEach(h => {
        if (String(h.type).toLowerCase() !== 'redeem') return;
        const desc = String(h.description || '');
        const match = desc.match(/^Redeemed:\s*(.+)$/i);
        if (!match) return;
        const name = match[1].trim();
        redeemCounts[name] = (redeemCounts[name] || 0) + 1;
    });

    document.querySelectorAll('#rewardsGrid .reward-card').forEach(card => {
        const btn = card.querySelector('.btn-redeem');
        const cost = parseInt(card.dataset.rewardPts || '0', 10);
        const lockEl = card.querySelector('.reward-lock-icon');
        const iconEl = card.querySelector('.reward-svg-icon');
        const nameEl = card.querySelector('.reward-name');
        if (!btn) return;

        const rewardName = nameEl ? nameEl.textContent.trim() : '';
        const timesRedeemed = redeemCounts[rewardName] || 0;
        const can = balance >= cost;

        // Remove old badge and voucher button
        card.querySelector('.reward-redeemed-badge')?.remove();
        card.querySelector('.reward-voucher-btn-wrap')?.remove();

        // Count vouchers for this reward
        const voucherCount = (_allVouchers || []).filter(v => v.reward_name === rewardName).length;
        const activeCount = (_allVouchers || []).filter(v => v.reward_name === rewardName && v.status === 'active').length;

        // Add redeemed badge
        if (voucherCount > 0) {
            const badge = document.createElement('div');
            badge.className = 'reward-redeemed-badge';
            badge.innerHTML = `<svg viewBox="0 0 24 24" fill="none" style="width:10px;height:10px;stroke:currentColor;stroke-width:3;"><polyline points="20 6 9 17 4 12"/></svg> Redeemed${voucherCount > 1 ? ' ×' + voucherCount : ''}`;
            card.appendChild(badge);
        }

        // Add "My Vouchers" button below the reward foot
        if (voucherCount > 0) {
            const btnWrap = document.createElement('div');
            btnWrap.className = 'reward-voucher-btn-wrap';
            btnWrap.innerHTML = `
        <button class="reward-voucher-btn" onclick="openVouchersModal('${escHtml(rewardName)}')">
            <svg viewBox="0 0 24 24" fill="none" style="width:11px;height:11px;stroke:currentColor;stroke-width:1.8;">
                <rect x="2" y="7" width="20" height="12" rx="2"/>
                <path d="M2 11h20" stroke-width="1.3"/>
            </svg>
            My Vouchers
            ${activeCount > 0 ? `<span class="reward-voucher-count">${activeCount}</span>` : ''}
        </button>`;
            card.appendChild(btnWrap);
        }

        btn.disabled = !can;
        btn.innerHTML = can ?
            `<svg viewBox="0 0 24 24" fill="none" style="width:12px;height:12px;stroke:currentColor;stroke-width:2.5;"><polyline points="20 6 9 17 4 12"/></svg> Redeem` :
            `<svg viewBox="0 0 24 24" fill="none" style="width:12px;height:12px;stroke:currentColor;stroke-width:2;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Need more`;
        if (lockEl) lockEl.innerHTML = can ? '' : `<svg viewBox="0 0 24 24" fill="none"><rect x="5" y="11" width="14" height="10" rx="2" stroke-width="1.6"/><path d="M8 11V7a4 4 0 018 0v4" stroke-width="1.6" stroke-linecap="round"/></svg>`;
        if (iconEl) iconEl.className = `reward-svg-icon ${can ? 'unlocked' : 'locked'}`;
        card.classList.toggle('reward-locked', !can);
    });
}

/* ── Render full loyalty state ── */
function renderLoyalty(payload) {
    const balance = Number(payload.balance || 0);
    currentPoints = Math.max(0, balance);
    const meta = getTierMeta(currentPoints);
    const nextName = meta.next ? meta.next.name : 'Diamond';
    const tierName = meta.current.name;

    const set = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    };

    set('loyaltyPointsNum', currentPoints.toLocaleString());
    set('loyaltyTierName', tierName);

    const subEl = document.getElementById('loyaltyPointsSub');
    if (subEl) {
        subEl.innerHTML = meta.ptsToNext > 0 ?
            `<svg viewBox="0 0 24 24" fill="none" style="width:12px;height:12px;stroke:currentColor;stroke-width:2;"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg> ${meta.ptsToNext.toLocaleString()} pts to ${nextName}` :
            `<svg viewBox="0 0 24 24" fill="none" style="width:12px;height:12px;stroke:currentColor;stroke-width:2;"><polyline points="20 6 9 17 4 12"/></svg> Highest tier reached!`;
    }

    /* Update tier icon (SVG, no emoji) */
    const tierIconWrap = document.getElementById('loyaltyTierIcon');
    if (tierIconWrap) tierIconWrap.innerHTML = tierSVGs[tierName] || tierSVGs['Silver'];

    /* Update tier badge data attribute for CSS theming */
    const tierBadge = document.getElementById('loyaltyTierBadge');
    if (tierBadge) tierBadge.dataset.tier = tierName.toLowerCase();

    const fill = document.getElementById('progressFill');
    if (fill) fill.style.width = `${meta.progress}%`;

    const lLeft = document.getElementById('loyaltyProgressLeft');
    const lRight = document.getElementById('loyaltyProgressRight');
    if (lLeft) lLeft.innerHTML = `<span class="prog-dot"></span> ${tierName} &nbsp;·&nbsp; ${currentPoints.toLocaleString()} pts`;
    if (lRight) lRight.innerHTML = `${nextName} &nbsp;·&nbsp; ${meta.tierTotal.toLocaleString()} pts <span class="prog-dot"></span>`;

    set('summaryBalance', `${currentPoints.toLocaleString()} pts`);
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

    renderRewards(currentPoints, payload.history || [], payload.vouchers || []);
    renderHistory(payload.history || []);
    renderVouchers(payload.vouchers || []);
}

/* ── Fetch fresh data ── */
function refreshLoyaltyData() {
    fetch('../../endpoints/user/loyalty.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) renderLoyalty(data);
        })
        .catch(() => {});
}

/* ── Init ── */
setTimeout(() => {
    const fill = document.getElementById('progressFill');
    if (fill) fill.style.width = window.__PS_LOYALTY__.progressPct + '%';
}, 250);

['redeemModal', 'redeemSuccessModal'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('click', e => {
        if (e.target.id === id) closeModal(id);
    });
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

// Render immediately from server-side data, don't wait for fetch
const _init = window.__PS_LOYALTY__;
renderHistory(_init.history || []);
renderRewards(currentPoints, _init.history || [], _init.vouchers || []);
renderVouchers(_init.vouchers || []);

// Then keep polling for live updates
refreshLoyaltyData();

function copyVoucherCode(code, btn) {
    navigator.clipboard.writeText(code).then(() => {
        const orig = btn.textContent;
        btn.textContent = '✓ Copied!';
        setTimeout(() => btn.textContent = orig, 2000);
    });
}

function renderVouchers(vouchers) {
    const wrap = document.getElementById('myVouchersList');
    if (!wrap) return;

    if (!vouchers.length) {
        wrap.innerHTML = `<div class="history-empty">
            <svg viewBox="0 0 24 24" fill="none"><rect x="2" y="7" width="20" height="12" rx="2" stroke-width="1.5"/><path d="M2 11h20" stroke-width="1.3"/></svg>
            <p>No vouchers yet. Redeem a reward to get one!</p>
        </div>`;
        return;
    }

    const statusMeta = {
        active: {
            cls: 'voucher-active',
            label: 'Active'
        },
        used: {
            cls: 'voucher-used',
            label: 'Used'
        },
        expired: {
            cls: 'voucher-expired',
            label: 'Expired'
        },
    };

    wrap.innerHTML = vouchers.map(v => {
        const meta = statusMeta[v.status] || statusMeta.active;
        const date = v.created_at ? new Date(v.created_at).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        }) : '';
        const copyBtn = v.status === 'active' ?
            `<button class="btn-secondary" style="font-size:.7rem;padding:5px 12px;margin-top:6px;"
                onclick="copyVoucherCode('${escHtml(v.voucher_code)}', this)">Copy Code</button>` :
            '';
        return `<div class="voucher-item ${meta.cls}">
            <div class="voucher-info">
                <div class="voucher-name">${escHtml(v.reward_name)}</div>
                <div class="voucher-code">${escHtml(v.voucher_code)}</div>
                <div class="voucher-date">${escHtml(date)} · ${Number(v.points_used).toLocaleString()} pts</div>
            </div>
            <div class="voucher-right">
                <span class="voucher-status-badge">${meta.label}</span>
                ${copyBtn}
            </div>
        </div>`;
    }).join('');
}

function openVouchersModal(rewardName) {
    document.getElementById('vouchersModalTitle').textContent = rewardName + ' — My Vouchers';

    const vouchers = (_allVouchers || []).filter(v => v.reward_name === rewardName);
    const listEl = document.getElementById('vouchersModalList');

    if (!vouchers.length) {
        listEl.innerHTML = `<div class="history-empty">
            <svg viewBox="0 0 24 24" fill="none"><rect x="2" y="7" width="20" height="12" rx="2" stroke-width="1.5"/><path d="M2 11h20" stroke-width="1.3"/></svg>
            <p>No vouchers for this reward yet.</p>
        </div>`;
    } else {
        const statusMeta = {
            active: {
                cls: 'voucher-active',
                label: 'Active'
            },
            used: {
                cls: 'voucher-used',
                label: 'Used'
            },
            expired: {
                cls: 'voucher-expired',
                label: 'Expired'
            },
        };
        listEl.innerHTML = vouchers.map(v => {
            const meta = statusMeta[v.status] || statusMeta.active;
            const date = v.created_at ? new Date(v.created_at).toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            }) : '';
            const copyBtn = v.status === 'active' ?
                `<button class="btn-secondary" style="font-size:.7rem;padding:5px 12px;margin-top:6px;"
                    onclick="copyVoucherCode('${escHtml(v.voucher_code)}', this)">Copy Code</button>` :
                '';
            return `<div class="voucher-item ${meta.cls}">
                <div class="voucher-info">
                    <div class="voucher-code">${escHtml(v.voucher_code)}</div>
                    <div class="voucher-date">${escHtml(date)} · ${Number(v.points_used).toLocaleString()} pts</div>
                </div>
                <div class="voucher-right">
                    <span class="voucher-status-badge">${meta.label}</span>
                    ${copyBtn}
                </div>
            </div>`;
        }).join('');
    }

    openModal('myVouchersModal');
}