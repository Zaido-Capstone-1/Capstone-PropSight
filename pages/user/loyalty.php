<?php
include '../../includes/session.php';
require_not_blacklisted(false);
if ($_SESSION['role'] !== 'user') {
    echo '<!DOCTYPE html>
<html>
<head>

</head>
<body>

<script src="../../assets/js/user-js/loyalty-inline.js"></script>
</body>
</html>';
    exit;
}

$first_name = htmlspecialchars($_SESSION['first_name'] ?? $_SESSION['name'] ?? 'Guest');
$last_name  = htmlspecialchars($_SESSION['last_name']  ?? '');
$full_name  = trim($first_name . ' ' . $last_name);
$email      = htmlspecialchars($_SESSION['email'] ?? '');
$initials   = strtoupper(mb_substr($first_name, 0, 1) . mb_substr($last_name, 0, 1));
$hour       = (int)date('G');
$greeting   = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

$page_title     = 'Loyalty Points';
$page_hero_html = 'Loyalty <em>Points</em>';
$page_hero_sub  = 'Earn points every stay and redeem for free nights and exclusive perks.';
$page_hero_icon = '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/>';
$active_nav     = 'loyalty';
require '../../includes/_layout.php';
require_once '../../lib/user-queries/loyalty_queries.php';

$rewards = [
    ['name' => 'Free Night Stay', 'desc' => 'One complimentary night in any Standard room', 'pts' => 800, 'img' => '🏠'],
    ['name' => 'Room Upgrade', 'desc' => 'Upgrade to next room tier on your next booking', 'pts' => 400, 'img' => '⬆️'],
    ['name' => 'Free Breakfast', 'desc' => 'Complimentary breakfast for two guests', 'pts' => 150, 'img' => '🍳'],
    ['name' => 'Late Check-out', 'desc' => 'Check out at 2PM instead of 12PM', 'pts' => 100, 'img' => '🕑'],
    ['name' => 'Spa Voucher', 'desc' => '500 discount at partner spa centers', 'pts' => 300, 'img' => '💆'],
    ['name' => 'Airport Transfer', 'desc' => 'Free roundtrip transfer from nearest airport', 'pts' => 600, 'img' => '🚌'],
];
?>

<link rel="stylesheet" href="../../assets/css/user-css/loyalty.css" />

<div class="loyalty-hero-card reveal">
    <div class="lhc-inner">
        <div>
            <div class="lhc-points-label">Your Balance</div>
            <div class="lhc-points-num" id="loyaltyPointsNum"><?php echo number_format($points); ?></div>
            <div class="lhc-points-sub" id="loyaltyPointsSub">points · <?php echo $pts_to_next; ?> pts to <?php echo $next_tier; ?></div>
        </div>
        <div class="lhc-tier-badge">
            <div id="loyaltyTierIcon" style="font-size:2rem;margin-bottom:4px;"><?php echo $tier_icon; ?></div>
            <div class="lhc-tier-name" id="loyaltyTierName"><?php echo $tier; ?></div>
            <div class="lhc-tier-sub">Member</div>
        </div>
    </div>
    <div class="progress-bar-wrap">
        <div class="progress-label">
            <span id="loyaltyProgressLeft"><?php echo $tier; ?> (<?php echo number_format($points); ?> pts)</span>
            <span id="loyaltyProgressRight"><?php echo $next_tier; ?> (<?php echo number_format($tier_total); ?> pts)</span>
        </div>
        <div class="progress-track">
            <div class="progress-fill" id="progressFill" style="width:0%"></div>
        </div>
    </div>
</div>

<div class="page-two-col">
    <div class="col-main">

        <div class="card reveal rd1">
            <div class="card-title"><svg viewBox="0 0 24 24">
                    <circle cx="12" cy="8" r="6" />
                    <path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11" />
                </svg>Membership Tiers</div>
            <div class="tiers-strip" id="tiersStrip">
                <?php foreach ($tiers as $t): ?>
                    <div class="tier-card <?php echo $t['active'] ? 'active-tier' : ''; ?>">
                        <div class="tier-icon"><?php echo $t['icon']; ?></div>
                        <div class="tier-name"><?php echo $t['name']; ?></div>
                        <div class="tier-range"><?php echo $t['min']; ?>+ pts<?php echo $t['max'] ? ' – ' . number_format($t['max']) . ' pts' : ''; ?></div>
                        <?php if ($t['active']): ?><div class="tier-current-badge">Current Tier</div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card reveal rd2">
            <div class="card-title"><svg viewBox="0 0 24 24">
                    <path d="M20 12V22H4V12" />
                    <path d="M22 7H2v5h20V7z" />
                    <path d="M12 22V7" />
                    <path d="M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7z" />
                    <path d="M12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z" />
                </svg>Redeem Rewards</div>
            <div class="rewards-grid" id="rewardsGrid">
                <?php foreach ($rewards as $r): $can = $points >= $r['pts']; ?>
                    <div class="reward-card">
                        <div class="reward-icon"><?php echo $r['img']; ?></div>
                        <div class="reward-name"><?php echo $r['name']; ?></div>
                        <div class="reward-desc"><?php echo $r['desc']; ?></div>
                        <div class="reward-foot">
                            <div class="reward-cost"><?php echo number_format($r['pts']); ?> <span>pts</span></div>
                            <button class="btn-redeem" <?php echo !$can ? 'disabled' : ''; ?>
                                onclick="redeemReward('<?php echo $r['name']; ?>',<?php echo $r['pts']; ?>)">
                                <?php echo $can ? 'Redeem' : 'Need more'; ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card reveal rd3">
            <div class="card-title"><svg viewBox="0 0 24 24">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                </svg>Points History</div>
            <div id="loyaltyHistoryList">
                <?php foreach ($history as $h): ?>
                    <div class="history-item">
                        <div class="h-dot <?php echo $h['type']; ?>">
                            <?php if ($h['type'] === 'earn'): ?>
                                <svg viewBox="0 0 24 24">
                                    <line x1="12" y1="19" x2="12" y2="5" />
                                    <polyline points="5 12 12 5 19 12" />
                                </svg>
                            <?php elseif ($h['type'] === 'redeem'): ?>
                                <svg viewBox="0 0 24 24">
                                    <line x1="12" y1="5" x2="12" y2="19" />
                                    <polyline points="19 12 12 19 5 12" />
                                </svg>
                            <?php else: ?>
                                <svg viewBox="0 0 24 24">
                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                                </svg>
                            <?php endif; ?>
                        </div>
                        <div class="h-desc">
                            <div class="h-desc-main"><?php echo $h['desc']; ?></div>
                            <div class="h-desc-date"><?php echo $h['date']; ?></div>
                        </div>
                        <div class="h-pts <?php echo $h['type']; ?>"><?php echo $h['pts']; ?> pts</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div><!-- /col-main -->

    <!-- ── Loyalty Sidebar ── -->
    <div class="col-side">

        <div class="widget-card reveal rd1">
            <div class="widget-title">
                <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="8" r="6" />
                    <path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11" />
                </svg>
                Points Summary
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Current Balance</span>
                <span class="mini-stat-val" id="summaryBalance"><?php echo number_format($points); ?> pts</span>
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Current Tier</span>
                <span class="mini-stat-val" id="summaryTier"><?php echo $tier_icon . ' ' . $tier; ?></span>
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Next Tier</span>
                <span class="mini-stat-val" id="summaryNextTier"><?php echo $next_tier; ?></span>
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Points Needed</span>
                <span class="mini-stat-val" id="summaryPtsNeeded"><?php echo $pts_to_next > 0 ? number_format($pts_to_next) . ' pts' : '—'; ?></span>
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Progress</span>
                <span class="mini-stat-val" id="summaryProgress"><?php echo $progress_pct; ?>%</span>
            </div>
        </div>

        <div class="tip-card reveal rd2">
            <div class="tip-card-label">💡 Earn faster</div>
            <div class="tip-card-title">Double points this month!</div>
            <div class="tip-card-body">Book any room between now and end of month to earn 2× loyalty points on your stay.</div>
            <a href="../../index.php" class="tip-card-cta">Browse Rooms →</a>
        </div>

        <div class="widget-card reveal rd3">
            <div class="widget-title">
                <svg viewBox="0 0 24 24">
                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                </svg>
                How to Earn
            </div>
            <div class="activity-item">
                <div class="activity-dot green"></div>
                <div class="activity-desc"><strong>1 pt</strong> per ₱10 spent on bookings</div>
            </div>
            <div class="activity-item">
                <div class="activity-dot gold"></div>
                <div class="activity-desc"><strong>Bonus pts</strong> on your birthday</div>
            </div>
            <div class="activity-item">
                <div class="activity-dot"></div>
                <div class="activity-desc"><strong>Referral bonus</strong> when friends book</div>
            </div>
            <div class="activity-item">
                <div class="activity-dot green"></div>
                <div class="activity-desc"><strong>Promo pts</strong> during special events</div>
            </div>
        </div>

    </div><!-- /col-side -->
</div><!-- /page-two-col -->

<div class="modal-overlay" id="redeemModal">
    <div class="modal-box" style="max-width:400px;text-align:center;">
        <button class="modal-close-btn" onclick="closeModal('redeemModal')">✕</button>
        <div id="redeemIcon" style="font-size:2.8rem;margin-bottom:12px;"></div>
        <div class="modal-title" id="redeemName"></div>
        <p id="redeemDesc" style="font-size:0.84rem;color:var(--text-soft);margin:8px 0 6px;line-height:1.65;"></p>
        <p style="font-size:0.82rem;color:var(--text-mid);margin-bottom:20px;">
            This will deduct <strong id="redeemCost" style="color:var(--blue-500);"></strong> from your balance.<br>
            Remaining: <strong id="redeemRemaining" style="color:var(--blue-500);"></strong>
        </p>
        <div style="display:flex;gap:10px;justify-content:center;">
            <button class="btn-secondary" onclick="closeModal('redeemModal')">Cancel</button>
            <button class="btn-primary" id="redeemConfirmBtn" onclick="confirmRedeem()">
                <svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.5;">
                    <polyline points="20 6 9 17 4 12" />
                </svg>
                Redeem Now
            </button>
        </div>
    </div>
</div>

<script>
window.__PS_LOYALTY__ = {
  currentPoints: <?php echo (int)$points; ?>,
  rewardData:    <?php echo json_encode($rewards, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
  progressPct:   <?php echo (float)$progress_pct; ?>
};
</script>
<script src="../../assets/js/user-js/loyalty.js"></script>
<script>window.PS_RT_PAGE = 'loyalty';</script>
<?php require '../../includes/_layout_end.php'; ?>