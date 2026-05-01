let pendingReward = null;
    let currentPoints = window.__PS_LOYALTY__.currentPoints;
    let loyaltyRefreshTimer = null;

    const tierDefs = [
        { name: 'Silver', min: 0, max: 499, icon: '🥈' },
        { name: 'Gold', min: 500, max: 1999, icon: '🥇' },
        { name: 'Platinum', min: 2000, max: 4999, icon: '💎' },
        { name: 'Diamond', min: 5000, max: null, icon: '👑' }
    ];

    function openModal(id) {
        document.getElementById(id).classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
        document.body.style.overflow = '';
    }

    const rewardData = window.__PS_LOYALTY__.rewardData;
    const escHtml = (v) => String(v ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

    function redeemReward(name, pts) {
        pendingReward = {
            name,
            pts
        };
        const r = rewardData.find(x => x.name === name);
        document.getElementById('redeemIcon').textContent = r ? r.img : '🎁';
        document.getElementById('redeemName').textContent = name;
        document.getElementById('redeemDesc').textContent = r ? r.desc : '';
        document.getElementById('redeemCost').textContent = pts.toLocaleString() + ' pts';
        document.getElementById('redeemRemaining').textContent = (currentPoints - pts).toLocaleString() + ' pts';
        openModal('redeemModal');
    }

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
        const progress = next ? Math.min(100, Math.round(((points - tierBase) / (tierTotal - tierBase)) * 100)) : 100;
        return { current, next, ptsToNext, tierTotal, progress };
    }

    function renderHistory(items) {
        const wrap = document.getElementById('loyaltyHistoryList');
        if (!wrap) return;
        const list = Array.isArray(items) ? items : [];
        if (!list.length) {
            wrap.innerHTML = `
                <div class="history-item">
                    <div class="h-dot bonus"><svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></div>
                    <div class="h-desc">
                        <div class="h-desc-main">Welcome! Start booking to earn points.</div>
                        <div class="h-desc-date">Today</div>
                    </div>
                    <div class="h-pts bonus">+0 pts</div>
                </div>`;
            return;
        }
        wrap.innerHTML = list.slice(0, 30).map(h => {
            const t = String(h.type || 'bonus').toLowerCase();
            const safeType = ['earn', 'redeem', 'bonus'].includes(t) ? t : 'bonus';
            const pts = Number(h.points || 0);
            const ptsText = `${pts >= 0 ? '+' : ''}${pts.toLocaleString()} pts`;
            const dt = h.created_at ? new Date(h.created_at) : null;
            const dateText = dt && !Number.isNaN(dt.getTime())
                ? dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
                : '';
            let icon = '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />';
            if (t === 'earn') icon = '<line x1="12" y1="19" x2="12" y2="5" /><polyline points="5 12 12 5 19 12" />';
            if (t === 'redeem') icon = '<line x1="12" y1="5" x2="12" y2="19" /><polyline points="19 12 12 19 5 12" />';
            return `
                <div class="history-item">
                    <div class="h-dot ${safeType}">
                        <svg viewBox="0 0 24 24">${icon}</svg>
                    </div>
                    <div class="h-desc">
                        <div class="h-desc-main">${escHtml(h.description || 'Loyalty update')}</div>
                        <div class="h-desc-date">${escHtml(dateText)}</div>
                    </div>
                    <div class="h-pts ${safeType}">${ptsText}</div>
                </div>`;
        }).join('');
    }

    function renderRewards(balance) {
        const cards = document.querySelectorAll('#rewardsGrid .reward-card');
        cards.forEach(card => {
            const btn = card.querySelector('.btn-redeem');
            const costEl = card.querySelector('.reward-cost');
            if (!btn || !costEl) return;
            const cost = parseInt(costEl.textContent.replace(/[^\d]/g, ''), 10) || 0;
            const can = balance >= cost;
            btn.disabled = !can;
            btn.textContent = can ? 'Redeem' : 'Need more';
        });
    }

    function renderLoyalty(payload) {
        const balance = Number(payload.balance || 0);
        currentPoints = Math.max(0, balance);
        const meta = getTierMeta(currentPoints);
        const nextTierName = meta.next ? meta.next.name : 'Diamond';

        const pointsNum = document.getElementById('loyaltyPointsNum');
        const pointsSub = document.getElementById('loyaltyPointsSub');
        const tierName = document.getElementById('loyaltyTierName');
        const tierIcon = document.getElementById('loyaltyTierIcon');
        const progressFill = document.getElementById('progressFill');
        const pLeft = document.getElementById('loyaltyProgressLeft');
        const pRight = document.getElementById('loyaltyProgressRight');

        if (pointsNum) pointsNum.textContent = currentPoints.toLocaleString();
        if (pointsSub) pointsSub.textContent = `points · ${meta.ptsToNext} pts to ${nextTierName}`;
        if (tierName) tierName.textContent = meta.current.name;
        if (tierIcon) tierIcon.textContent = meta.current.icon;
        if (pLeft) pLeft.textContent = `${meta.current.name} (${currentPoints.toLocaleString()} pts)`;
        if (pRight) pRight.textContent = `${nextTierName} (${meta.tierTotal.toLocaleString()} pts)`;
        if (progressFill) progressFill.style.width = `${meta.progress}%`;

        const summaryBalance = document.getElementById('summaryBalance');
        const summaryTier = document.getElementById('summaryTier');
        const summaryNext = document.getElementById('summaryNextTier');
        const summaryNeed = document.getElementById('summaryPtsNeeded');
        const summaryProg = document.getElementById('summaryProgress');
        if (summaryBalance) summaryBalance.textContent = `${currentPoints.toLocaleString()} pts`;
        if (summaryTier) summaryTier.textContent = `${meta.current.icon} ${meta.current.name}`;
        if (summaryNext) summaryNext.textContent = nextTierName;
        if (summaryNeed) summaryNeed.textContent = meta.ptsToNext > 0 ? `${meta.ptsToNext.toLocaleString()} pts` : '—';
        if (summaryProg) summaryProg.textContent = `${meta.progress}%`;

        renderRewards(currentPoints);
        renderHistory(payload.history || []);
    }

    function refreshLoyaltyData() {
        fetch('../../api/user/loyalty.php')
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                renderLoyalty(data);
            })
            .catch(() => {});
    }

    function confirmRedeem() {
        const btn = document.getElementById('redeemConfirmBtn');
        btn.disabled = true;
        btn.textContent = 'Processing...';
        const fd = new FormData();
        fd.append('action', 'redeem');
        fd.append('reward_name', pendingReward.name);
        fd.append('points', pendingReward.pts);
        if (typeof window.psAppendCsrf === 'function') window.psAppendCsrf(fd);
        fetch('../../api/user/loyalty.php', {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    showToast(data.message || 'Error occurred', 'error');
                    btn.disabled = false;
                    btn.textContent = 'Redeem Now';
                    return;
                }
                refreshLoyaltyData();
                closeModal('redeemModal');
                btn.disabled = false;
                btn.textContent = 'Redeem Now';
                showToast('"' + pendingReward.name + '" redeemed successfully!');
            })
            .catch(() => {
                showToast('Network error. Try again.', 'error');
                btn.disabled = false;
                btn.textContent = 'Redeem Now';
            });
    }

    setTimeout(() => {
        document.getElementById('progressFill').style.width = window.__PS_LOYALTY__.progressPct + '%';
    }, 250);

    document.getElementById('redeemModal').addEventListener('click', e => {
        if (e.target.id === 'redeemModal') closeModal('redeemModal');
    });
    window.addEventListener('ps:user_metrics', refreshLoyaltyData);
    loyaltyRefreshTimer = setInterval(refreshLoyaltyData, 8000);
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            closeModal('redeemModal');
            closeSidebar();
        }
    });
    refreshLoyaltyData();
