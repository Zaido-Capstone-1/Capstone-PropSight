<?php
include '../../includes/session.php';
require_not_blacklisted(false);
if ($_SESSION['role'] !== 'user') {
    echo '<!DOCTYPE html><html><head></head><body><script src="../../assets/js/user-js/loyalty-inline.js"></script></body></html>';
    exit;
}

$first_name = htmlspecialchars($_SESSION['first_name'] ?? $_SESSION['name'] ?? 'Guest');
$last_name = htmlspecialchars($_SESSION['last_name'] ?? '');
$full_name = trim($first_name . ' ' . $last_name);
$email = htmlspecialchars($_SESSION['email'] ?? '');
$initials = strtoupper(mb_substr($first_name, 0, 1) . mb_substr($last_name, 0, 1));
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

$page_title = 'Loyalty Points';
$page_hero_html = 'Loyalty <em>Points</em>';
$page_hero_sub = 'Earn points every stay and redeem for free nights and exclusive perks.';
$page_hero_icon = '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/>';
$active_nav = 'loyalty';
require '../../includes/_layout.php';
require_once '../../lib/user-queries/loyalty_queries.php';

/* ── SVG icon map for tiers ── */
$tier_svgs = [
    'Silver' => '<svg class="tier-svg silver" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="15" r="6" stroke-width="1.5"/><path d="M10 13c0-1 4-1 4 0s-4 1-4 2 4 1 4 0" stroke-width="1.4" stroke-linecap="round"/><path d="M9 3h6l-1.5 5h-3L9 3z" stroke-width="1.4" stroke-linejoin="round"/></svg>',
    'Gold' => '<svg class="tier-svg gold"     viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><polygon points="12,2 15.5,8.5 23,9.5 17.5,14.8 19,22 12,18.3 5,22 6.5,14.8 1,9.5 8.5,8.5" stroke-width="1.5" stroke-linejoin="round"/></svg>',
    'Platinum' => '<svg class="tier-svg platinum" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2L6 7H2l2 5-2 5h4l6 5 6-5h4l-2-5 2-5h-4L12 2z" stroke-width="1.5" stroke-linejoin="round"/></svg>',
    'Diamond' => '<svg class="tier-svg diamond"  viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2 9l3-6h14l3 6-10 12L2 9z" stroke-width="1.5" stroke-linejoin="round"/><path d="M2 9h20M8 3l-3 6 7 12M16 3l3 6-7 12" stroke-width="1.2" stroke-linecap="round"/></svg>',
];

$rewards = [];
$rwStmt = $conn->query(
    "SELECT reward_id AS id, name, description AS `desc`, points_cost AS pts
     FROM loyalty_rewards
     WHERE is_active = 1
     ORDER BY points_cost ASC"
);
if ($rwStmt) {
    while ($rw = $rwStmt->fetch_assoc()) {
        $rewards[] = $rw;
    }
}

// Replace $reward_svg_pool with this:

$reward_svg_map = [
    'night' => '<svg viewBox="0 0 24 24" fill="none"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 01-1 1H4a1 1 0 01-1-1V9.5z" stroke-width="1.6" stroke-linejoin="round"/><path d="M9 21V12h6v9" stroke-width="1.6" stroke-linejoin="round"/></svg>',
    'upgrade' => '<svg viewBox="0 0 24 24" fill="none"><rect x="3" y="14" width="18" height="7" rx="1.5" stroke-width="1.6"/><rect x="5" y="9" width="14" height="5" rx="1" stroke-width="1.6"/><path d="M12 9V3m-3 3l3-3 3 3" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'breakfast' => '<svg viewBox="0 0 24 24" fill="none"><path d="M18 8h1a4 4 0 010 8h-1" stroke-width="1.6" stroke-linecap="round"/><path d="M2 8h16v9a4 4 0 01-4 4H6a4 4 0 01-4-4V8z" stroke-width="1.6" stroke-linejoin="round"/><line x1="6" y1="2" x2="6" y2="4" stroke-width="1.8" stroke-linecap="round"/><line x1="10" y1="2" x2="10" y2="4" stroke-width="1.8" stroke-linecap="round"/><line x1="14" y1="2" x2="14" y2="4" stroke-width="1.8" stroke-linecap="round"/></svg>',
    'checkout' => '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke-width="1.6"/><polyline points="12,7 12,12 16,14" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'spa' => '<svg viewBox="0 0 24 24" fill="none"><path d="M12 22V12" stroke-width="1.6" stroke-linecap="round"/><path d="M12 12C12 12 7 10 5 5c3 0 7 2 7 7z" stroke-width="1.5" stroke-linejoin="round"/><path d="M12 12C12 12 17 10 19 5c-3 0-7 2-7 7z" stroke-width="1.5" stroke-linejoin="round"/><path d="M5 19c2-1 4-2 7-2s5 1 7 2" stroke-width="1.5" stroke-linecap="round"/></svg>',
    'transfer' => '<svg viewBox="0 0 24 24" fill="none"><rect x="2" y="7" width="20" height="12" rx="2" stroke-width="1.6"/><path d="M7 19v2M17 19v2" stroke-width="1.8" stroke-linecap="round"/><circle cx="7" cy="14" r="1.5" fill="currentColor" opacity=".7"/><circle cx="17" cy="14" r="1.5" fill="currentColor" opacity=".7"/><path d="M2 11h20" stroke-width="1.4"/><path d="M12 7V5M8 5h8" stroke-width="1.5" stroke-linecap="round"/></svg>',
    'discount' => '<svg viewBox="0 0 24 24" fill="none"><circle cx="9" cy="9" r="2" stroke-width="1.6"/><circle cx="15" cy="15" r="2" stroke-width="1.6"/><path d="M5 19L19 5" stroke-width="1.6" stroke-linecap="round"/><rect x="3" y="3" width="18" height="18" rx="3" stroke-width="1.6"/></svg>',
    'parking' => '<svg viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="18" height="18" rx="2" stroke-width="1.6"/><path d="M9 17V7h4a3 3 0 010 6H9" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'gift' => '<svg viewBox="0 0 24 24" fill="none"><path d="M20 12V22H4V12"/><path d="M22 7H2v5h20V7z" stroke-linejoin="round"/><path d="M12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z"/></svg>',
];

function resolve_reward_icon(string $name, string $desc, array $map): string
{
    $haystack = strtolower($name . ' ' . $desc);
    $rules = [
        'night' => ['night', 'stay', 'room', 'accommodation'],
        'upgrade' => ['upgrade', 'tier', 'suite', 'premium'],
        'breakfast' => ['breakfast', 'meal', 'dining', 'food', 'lunch', 'dinner', 'brunch'],
        'checkout' => ['check-out', 'checkout', 'late', 'early check-in', 'checkin'],
        'spa' => ['spa', 'massage', 'wellness', 'relax'],
        'transfer' => ['transfer', 'airport', 'shuttle', 'transport', 'pickup'],
        'discount' => ['discount', 'voucher', 'off', 'cashback', 'rebate', 'promo'],
        'parking' => ['parking', 'garage', 'valet'],
    ];
    foreach ($rules as $icon_key => $keywords) {
        foreach ($keywords as $kw) {
            if (str_contains($haystack, $kw)) {
                return $map[$icon_key];
            }
        }
    }
    return $map['gift']; // fallback
}

?>

<link rel="stylesheet" href="../../assets/css/user-css/loyalty.css" />

<!-- ── Hero Balance Card ── -->
<div class="loyalty-hero-card reveal">

    <!-- decorative rings -->
    <div class="lhc-ring lhc-ring-1"></div>
    <div class="lhc-ring lhc-ring-2"></div>
    <div class="lhc-ring lhc-ring-3"></div>

    <div class="lhc-inner">

        <!-- Left: points -->
        <div class="lhc-left">
            <div class="lhc-points-label">
                <svg viewBox="0 0 24 24" fill="none" class="lhc-label-icon">
                    <circle cx="12" cy="8" r="5" stroke-width="1.8" />
                    <path d="M14.5 11.9L16 21l-4-2.4-4 2.4 1.5-9.1" stroke-width="1.6" stroke-linejoin="round" />
                </svg>
                Your Balance
            </div>
            <div class="lhc-points-num" id="loyaltyPointsNum"><?php echo number_format($points); ?></div>
            <div class="lhc-points-unit">LOYALTY POINTS</div>
            <div class="lhc-points-sub" id="loyaltyPointsSub">
                <?php if ($pts_to_next > 0): ?>
                    <svg viewBox="0 0 24 24" fill="none" style="width:12px;height:12px;stroke:currentColor;stroke-width:2;">
                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18" />
                        <polyline points="17 6 23 6 23 12" />
                    </svg>
                    <?php echo number_format($pts_to_next); ?> pts to <?php echo $next_tier; ?>
                <?php else: ?>
                    <svg viewBox="0 0 24 24" fill="none" style="width:12px;height:12px;stroke:currentColor;stroke-width:2;">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                    Highest tier reached!
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: tier badge -->
        <div class="lhc-tier-badge" id="loyaltyTierBadge" data-tier="<?php echo strtolower($tier); ?>">
            <div class="lhc-tier-icon-wrap" id="loyaltyTierIcon">
                <?php echo $tier_svgs[$tier] ?? $tier_svgs['Silver']; ?>
            </div>
            <div class="lhc-tier-name" id="loyaltyTierName"><?php echo $tier; ?></div>
            <div class="lhc-tier-sub">Member</div>
        </div>

    </div>

    <!-- Progress bar -->
    <div class="progress-bar-wrap">
        <div class="progress-label">
            <span id="loyaltyProgressLeft">
                <span class="prog-dot"></span>
                <?php echo $tier; ?> &nbsp;·&nbsp; <?php echo number_format($points); ?> pts
            </span>
            <span id="loyaltyProgressRight">
                <?php echo $next_tier; ?> &nbsp;·&nbsp; <?php echo number_format($tier_total); ?> pts
                <span class="prog-dot"></span>
            </span>
        </div>
        <div class="progress-track">
            <div class="progress-fill" id="progressFill" style="width:0%">
                <div class="progress-shine"></div>
            </div>
        </div>
    </div>

</div><!-- /loyalty-hero-card -->

<div class="page-two-col">
    <div class="col-main">

        <!-- ── Membership Tiers ── -->
        <div class="card reveal rd1">
            <div class="card-title">
                <svg viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="8" r="5" stroke-width="1.8" />
                    <path d="M14.5 11.9L16 21l-4-2.4-4 2.4 1.5-9.1" stroke-width="1.6" stroke-linejoin="round" />
                </svg>
                Membership Tiers
            </div>
            <div class="tiers-strip" id="tiersStrip">
                <?php foreach ($tiers as $t): ?>
                    <div class="tier-card <?php echo $t['active'] ? 'active-tier' : ''; ?>"
                        data-tier="<?php echo strtolower($t['name']); ?>">
                        <div class="tier-icon">
                            <?php echo $tier_svgs[$t['name']] ?? $tier_svgs['Silver']; ?>
                        </div>
                        <div class="tier-name"><?php echo $t['name']; ?></div>
                        <div class="tier-range">
                            <?php echo number_format($t['min']); ?>+
                            <?php echo $t['max'] ? '– ' . number_format($t['max']) . ' pts' : 'pts'; ?>
                        </div>
                        <?php if ($t['active']): ?>
                            <div class="tier-current-badge">
                                <svg viewBox="0 0 24 24" fill="none"
                                    style="width:9px;height:9px;stroke:currentColor;stroke-width:3;">
                                    <polyline points="20 6 9 17 4 12" />
                                </svg>
                                Current
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ── Redeem Rewards ── -->
        <div class="card reveal rd2">
            <div class="card-title">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M20 12V22H4V12" />
                    <path d="M22 7H2v5h20V7z" stroke-linejoin="round" />
                    <path d="M12 22V7" />
                    <path d="M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7z" />
                    <path d="M12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z" />
                </svg>
                Redeem Rewards
            </div>
            <p class="card-subtext">Balance: <strong id="rewardsBalanceDisplay"><?php echo number_format($points); ?>
                    pts</strong></p>
            <div class="rewards-grid" id="rewardsGrid">
                <?php foreach ($rewards as $loop_index => $r):
                    $can = $points >= $r['pts']; ?>
                    <div class="reward-card <?php echo !$can ? 'reward-locked' : ''; ?>"
                        data-reward-id="<?php echo $r['id']; ?>" data-reward-pts="<?php echo $r['pts']; ?>">
                        <div class="reward-svg-icon <?php echo $can ? 'unlocked' : 'locked'; ?>">
                            <?php echo resolve_reward_icon($r['name'], $r['desc'], $reward_svg_map); ?>
                        </div>
                        <div class="reward-lock-icon">
                            <?php if (!$can): ?>
                                <svg viewBox="0 0 24 24" fill="none">
                                    <rect x="5" y="11" width="14" height="10" rx="2" stroke-width="1.6" />
                                    <path d="M8 11V7a4 4 0 018 0v4" stroke-width="1.6" stroke-linecap="round" />
                                </svg>
                            <?php endif; ?>
                        </div>

                        <div class="reward-name"><?php echo htmlspecialchars($r['name']); ?></div>
                        <div class="reward-desc"><?php echo htmlspecialchars($r['desc']); ?></div>
                        <div class="reward-foot">
                            <div class="reward-cost">
                                <svg viewBox="0 0 24 24" fill="none" class="reward-cost-icon">
                                    <circle cx="12" cy="8" r="5" stroke-width="1.8" />
                                    <path d="M14.5 11.9L16 21l-4-2.4-4 2.4 1.5-9.1" stroke-width="1.5"
                                        stroke-linejoin="round" />
                                </svg>
                                <?php echo number_format($r['pts']); ?> <span>pts</span>
                            </div>
                            <button class="btn-redeem" <?php echo !$can ? 'disabled' : ''; ?>
                                data-id="<?php echo $r['id']; ?>"
                                data-name="<?php echo htmlspecialchars($r['name'], ENT_QUOTES); ?>"
                                data-pts="<?php echo $r['pts']; ?>" data-svg-id="<?php echo $loop_index; ?>"
                                data-desc="<?php echo htmlspecialchars($r['desc'], ENT_QUOTES); ?>"
                                onclick="redeemReward(this)">
                                <?php if ($can): ?>
                                    <svg viewBox="0 0 24 24" fill="none"
                                        style="width:12px;height:12px;stroke:currentColor;stroke-width:2.5;">
                                        <polyline points="20 6 9 17 4 12" />
                                    </svg>
                                    Redeem
                                <?php else: ?>
                                    <svg viewBox="0 0 24 24" fill="none"
                                        style="width:12px;height:12px;stroke:currentColor;stroke-width:2;">
                                        <line x1="12" y1="5" x2="12" y2="19" />
                                        <line x1="5" y1="12" x2="19" y2="12" />
                                    </svg>
                                    Need more
                                <?php endif; ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ── Points History ── -->
        <div class="card reveal rd3">
            <div class="card-title">
                <svg viewBox="0 0 24 24" fill="none">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" stroke-width="1.8" stroke-linecap="round"
                        stroke-linejoin="round" />
                </svg>
                Points History
            </div>
            <div id="loyaltyHistoryList" class="history-list">
                <?php if (empty($history)): ?>
                    <div class="history-empty">
                        <svg viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="9" stroke-width="1.5" />
                            <path d="M12 8v4l3 3" stroke-width="1.8" stroke-linecap="round" />
                        </svg>
                        <p>No activity yet. Start booking to earn points!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($history as $h): ?>
                        <div class="history-item">
                            <div class="h-dot <?php echo htmlspecialchars($h['type']); ?>">
                                <?php if ($h['type'] === 'earn'): ?>
                                    <svg viewBox="0 0 24 24" fill="none">
                                        <line x1="12" y1="19" x2="12" y2="5" stroke-width="2" />
                                        <polyline points="5 12 12 5 19 12" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round" />
                                    </svg>
                                <?php elseif ($h['type'] === 'redeem'): ?>
                                    <svg viewBox="0 0 24 24" fill="none">
                                        <path d="M20 12V22H4V12" />
                                        <path d="M22 7H2v5h20V7z" stroke-linejoin="round" />
                                        <path d="M12 22V7" />
                                    </svg>
                                <?php else: ?>
                                    <svg viewBox="0 0 24 24" fill="none">
                                        <polygon
                                            points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"
                                            stroke-width="1.6" stroke-linejoin="round" />
                                    </svg>
                                <?php endif; ?>
                            </div>
                            <div class="h-desc">
                                <div class="h-desc-main"><?php echo htmlspecialchars($h['desc']); ?></div>
                                <div class="h-desc-date"><?php echo htmlspecialchars($h['date']); ?></div>
                            </div>
                            <div class="h-pts <?php echo htmlspecialchars($h['type']); ?>">
                                <?php echo htmlspecialchars($h['pts']); ?> pts
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if (!empty($history)): ?>
                <div class="history-pagination" id="historyPagination">
                    <button class="page-btn" id="prevPageBtn" onclick="changePage('prev')" disabled>
                        <svg viewBox="0 0 24 24" fill="none">
                            <polyline points="15 18 9 12 15 6" />
                        </svg>
                    </button>
                    <div id="pageNumbers"></div>
                    <button class="page-btn" id="nextPageBtn" onclick="changePage('next')">
                        <svg viewBox="0 0 24 24" fill="none">
                            <polyline points="9 18 15 12 9 6" />
                        </svg>
                    </button>
                </div>
            <?php endif; ?>
        </div>

    </div><!-- /col-main -->

    <!-- ── Sidebar ── -->
    <div class="col-side">

        <div class="widget-card reveal rd1">
            <div class="widget-title">
                <svg viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="8" r="5" stroke-width="1.8" />
                    <path d="M14.5 11.9L16 21l-4-2.4-4 2.4 1.5-9.1" stroke-width="1.6" stroke-linejoin="round" />
                </svg>
                Points Summary
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Current Balance</span>
                <span class="mini-stat-val" id="summaryBalance"><?php echo number_format($points); ?> pts</span>
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Current Tier</span>
                <span class="mini-stat-val mini-stat-tier" id="summaryTier">
                    <span class="mini-tier-svg" id="summaryTierSvg"><?php echo $tier_svgs[$tier] ?? ''; ?></span>
                    <?php echo $tier; ?>
                </span>
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Next Tier</span>
                <span class="mini-stat-val" id="summaryNextTier"><?php echo $next_tier; ?></span>
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Points Needed</span>
                <span class="mini-stat-val"
                    id="summaryPtsNeeded"><?php echo $pts_to_next > 0 ? number_format($pts_to_next) . ' pts' : '—'; ?></span>
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Progress</span>
                <span class="mini-stat-val" id="summaryProgress"><?php echo $progress_pct; ?>%</span>
            </div>
        </div>

        <div class="tip-card reveal rd2">
            <div class="tip-card-label">
                <svg viewBox="0 0 24 24" fill="none" style="width:13px;height:13px;stroke:currentColor;stroke-width:2;">
                    <circle cx="12" cy="12" r="9" />
                    <line x1="12" y1="8" x2="12" y2="12" />
                    <line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
                Earn faster
            </div>
            <div class="tip-card-title">Double points this month!</div>
            <div class="tip-card-body">Book any room between now and end of month to earn 2× loyalty points on your
                stay.</div>
            <a href="../../index.php" class="tip-card-cta">
                Browse Rooms
                <svg viewBox="0 0 24 24" fill="none"
                    style="width:13px;height:13px;stroke:currentColor;stroke-width:2.5;">
                    <line x1="5" y1="12" x2="19" y2="12" />
                    <polyline points="12 5 19 12 12 19" />
                </svg>
            </a>
        </div>

        <div class="widget-card reveal rd3">
            <div class="widget-title">
                <svg viewBox="0 0 24 24" fill="none">
                    <polygon
                        points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"
                        stroke-width="1.6" stroke-linejoin="round" />
                </svg>
                How to Earn
            </div>
            <div class="activity-item">
                <div class="activity-icon earn">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6" stroke-width="1.8"
                            stroke-linecap="round" />
                    </svg>
                </div>
                <div class="activity-desc"><strong>1 pt</strong> per ₱10 spent on bookings</div>
            </div>
            <div class="activity-item">
                <div class="activity-icon birthday">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" stroke-width="1.6" stroke-linecap="round" />
                        <circle cx="12" cy="7" r="4" stroke-width="1.6" />
                    </svg>
                </div>
                <div class="activity-desc"><strong>Bonus pts</strong> on your birthday</div>
            </div>
            <div class="activity-item">
                <div class="activity-icon referral">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" stroke-width="1.6" stroke-linecap="round" />
                        <circle cx="9" cy="7" r="4" stroke-width="1.6" />
                        <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" stroke-width="1.6"
                            stroke-linecap="round" />
                    </svg>
                </div>
                <div class="activity-desc"><strong>Referral bonus</strong> when friends book</div>
            </div>
            <div class="activity-item">
                <div class="activity-icon promo">
                    <svg viewBox="0 0 24 24" fill="none">
                        <polygon
                            points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"
                            stroke-width="1.6" stroke-linejoin="round" />
                    </svg>
                </div>
                <div class="activity-desc"><strong>Promo pts</strong> during special events</div>
            </div>
        </div>

    </div><!-- /col-side -->
</div><!-- /page-two-col -->

<!-- ── Redeem Confirm Modal ── -->
<div class="modal-overlay" id="redeemModal">
    <div class="modal-box" style="max-width:420px;text-align:center;">
        <button class="modal-close-btn" onclick="closeModal('redeemModal')">
            <svg viewBox="0 0 24 24" fill="none" style="width:16px;height:16px;stroke:currentColor;stroke-width:2.5;">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
        </button>
        <div class="modal-reward-icon" id="redeemIcon"></div>
        <div class="modal-title" id="redeemName"></div>
        <p id="redeemDesc" style="font-size:0.84rem;color:var(--text-soft);margin:8px 0 6px;line-height:1.65;"></p>
        <div class="redeem-cost-box">
            <div class="redeem-cost-row">
                <span>Cost</span>
                <strong id="redeemCost" style="color:var(--blue-500);"></strong>
            </div>
            <div class="redeem-cost-row">
                <span>Remaining after</span>
                <strong id="redeemRemaining" style="color:var(--blue-500);"></strong>
            </div>
        </div>
        <div style="display:flex;gap:10px;justify-content:center;margin-top:20px;">
            <button class="btn-secondary" onclick="closeModal('redeemModal')">Cancel</button>
            <button class="btn-primary" id="redeemConfirmBtn" onclick="confirmRedeem()">
                <svg viewBox="0 0 24 24" fill="none"
                    style="width:15px;height:15px;stroke:currentColor;stroke-width:2.5;">
                    <polyline points="20 6 9 17 4 12" />
                </svg>
                Redeem Now
            </button>
        </div>
    </div>
</div>

<!-- ── Redeem Success Modal ── -->
<div class="modal-overlay" id="redeemSuccessModal">
    <div class="modal-box" style="max-width:420px;text-align:center;">
        <div class="redeem-success-anim">
            <svg class="success-checkmark" viewBox="0 0 52 52">
                <circle class="success-circle" cx="26" cy="26" r="25" fill="none" />
                <path class="success-check" fill="none" d="M14 27l8 8 16-16" />
            </svg>
        </div>
        <div class="modal-title" style="color:var(--sage);">Reward Redeemed!</div>
        <p id="successRewardName" style="font-size:1rem;font-weight:700;color:var(--ink);margin:8px 0 4px;"></p>
        <p id="successRewardDesc" style="font-size:0.82rem;color:var(--text-soft);margin-bottom:18px;line-height:1.65;">
        </p>
        <div class="voucher-box" id="voucherBox">
            <div>
                <div class="voucher-label">Your Voucher Code</div>
                <div class="voucher-code" id="voucherCode">—</div>
            </div>
            <button class="voucher-copy-btn" id="voucherCopyBtn" onclick="copyVoucher()">
                <svg viewBox="0 0 24 24" fill="none" style="width:13px;height:13px;stroke:currentColor;stroke-width:2;">
                    <rect x="9" y="9" width="13" height="13" rx="2" />
                    <path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1" />
                </svg>
                Copy
            </button>
        </div>
        <div class="success-balance-info">
            New balance: <strong id="successNewBalance"></strong>
        </div>
        <p style="font-size:0.75rem;color:var(--ink-faint);margin-top:6px;">Present this code at the front desk to claim
            your reward.</p>
        <button class="btn-primary" style="margin-top:18px;" onclick="closeModal('redeemSuccessModal')">Done</button>
    </div>
</div>

<div class="modal-overlay" id="myVouchersModal">
    <div class="modal-box" style="max-width:480px;">
        <button class="modal-close-btn" onclick="closeModal('myVouchersModal')">
            <svg viewBox="0 0 24 24" fill="none" style="width:16px;height:16px;stroke:currentColor;stroke-width:2.5;">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
        </button>
        <div class="modal-title" id="vouchersModalTitle">My Vouchers</div>
        <div id="vouchersModalList" style="margin-top:16px;"></div>
    </div>
</div>

<script>
    window.__PS_LOYALTY__ = {
        currentPoints: <?php echo (int) $points; ?>,
        rewardData: <?php echo json_encode($rewards, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        progressPct: <?php echo (float) $progress_pct; ?>,
        history: <?php echo json_encode(array_map(fn($h) => [
            'points' => (int) preg_replace('/[^0-9\-]/', '', $h['pts']),
            'type' => $h['type'],
            'description' => $h['desc'],
            'created_at' => $h['date'],
        ], $history), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        vouchers: <?php echo json_encode($vouchers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
    };
</script>
<script src="../../assets/js/user-js/loyalty.js"></script>
<script>window.PS_RT_PAGE = 'loyalty';</script>
<?php require '../../includes/_layout_end.php'; ?>