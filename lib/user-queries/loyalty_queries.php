<?php
/**
 * lib/user-queries/loyalty_queries.php
 * Runs before the loyalty page renders.
 * Sets: $points, $tier, $tier_icon, $tiers, $next_tier,
 *       $tier_total, $pts_to_next, $progress_pct, $history
 */
require_once '../../includes/db.php';

$userId = (int) $_SESSION['user_id'];

/* ── 1. Auto-heal: award missing points for completed bookings ── */
$syncStmt = $conn->prepare("
    SELECT b.booking_id, b.total_amount
    FROM bookings b
    LEFT JOIN loyalty_points lp
           ON lp.booking_id = b.booking_id
          AND lp.user_id    = b.user_id
          AND lp.type       = 'earn'
    WHERE b.user_id = ?
      AND b.status  = 'completed'
      AND lp.id IS NULL
");
$syncStmt->bind_param('i', $userId);
$syncStmt->execute();
$syncRes = $syncStmt->get_result();

while ($sr = $syncRes->fetch_assoc()) {
    $syncBookingId = (int) $sr['booking_id'];
    $syncAmount = (float) $sr['total_amount'];
    if ($syncBookingId <= 0)
        continue;

    $syncPts = max(1, (int) floor($syncAmount / 10));  // ₱10 = 1 point
    $syncDesc = "Booking #{$syncBookingId} stay completed";

    $insStmt = $conn->prepare(
        "INSERT INTO loyalty_points (user_id, points, type, description, booking_id)
         VALUES (?, ?, 'earn', ?, ?)"
    );
    $insStmt->bind_param('iisi', $userId, $syncPts, $syncDesc, $syncBookingId);
    $insStmt->execute();
    $insStmt->store_result();
    $insStmt->free_result();
    $insStmt->close();

    while ($conn->more_results()) {
        $conn->next_result();
    }
}
$syncStmt->close();

/* ── 2. Current balance ── */
$balStmt = $conn->prepare('SELECT COALESCE(SUM(points), 0) AS bal FROM loyalty_points WHERE user_id = ?');
$balStmt->bind_param('i', $userId);
$balStmt->execute();
$points = max(0, (int) $balStmt->get_result()->fetch_assoc()['bal']);
$balStmt->close();

/* ── 3. Tier definitions & computed tier ── */
$tier_defs = [
    ['name' => 'Silver', 'min' => 0, 'max' => 499, 'icon' => '🥈'],
    ['name' => 'Gold', 'min' => 500, 'max' => 1999, 'icon' => '🥇'],
    ['name' => 'Platinum', 'min' => 2000, 'max' => 4999, 'icon' => '💎'],
    ['name' => 'Diamond', 'min' => 5000, 'max' => null, 'icon' => '👑'],
];

$current_tier_data = $tier_defs[0];
foreach ($tier_defs as $td) {
    if ($points >= $td['min'])
        $current_tier_data = $td;
}

$tier = $current_tier_data['name'];
$tier_icon = $current_tier_data['icon'];
$tiers = array_map(fn($t) => array_merge($t, ['active' => $t['name'] === $tier]), $tier_defs);

$tier_idx = array_search($tier, array_column($tier_defs, 'name'));
$next_td = $tier_defs[$tier_idx + 1] ?? null;
$next_tier = $next_td['name'] ?? 'Diamond';
$tier_total = $next_td ? $next_td['min'] : ($points + 1000);
$pts_to_next = $next_td ? max(0, $next_td['min'] - $points) : 0;
$tier_base = $current_tier_data['min'];
$progress_pct = $next_td
    ? min(100, (int) round((($points - $tier_base) / max(1, $tier_total - $tier_base)) * 100))
    : 100;

/* ── 4. Points history (most recent 30) ── */
$histStmt = $conn->prepare(
    "SELECT points, type, description, created_at
     FROM loyalty_points
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT 30"
);
$histStmt->bind_param('i', $userId);
$histStmt->execute();
$histRes = $histStmt->get_result();

$history = [];
while ($row = $histRes->fetch_assoc()) {
    $pts_val = (int) $row['points'];
    $history[] = [
        'date' => date('M j, Y', strtotime($row['created_at'])),
        'desc' => $row['description'],
        'pts' => ($pts_val >= 0 ? '+' : '') . number_format($pts_val),
        'type' => $row['type'],
    ];
}
$histStmt->close();

// if (empty($history)) {
//     $history[] = [
//         'date' => date('M j, Y'),
//         'desc' => 'Welcome! Start booking to earn points.',
//         'pts' => '+0',
//         'type' => 'bonus',
//     ];
// }

/* ── 5. Vouchers ── */
$vouchers = [];
// Free any lingering result sets before running next query
while ($conn->more_results()) {
    $conn->next_result();
}

$voucherStmt = $conn->prepare("
    SELECT reward_name, voucher_code, points_used, status, created_at
    FROM loyalty_redemptions
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 20
");
$voucherStmt->bind_param('i', $userId);
$voucherStmt->execute();
$voucherResult = $voucherStmt->get_result();
while ($row = $voucherResult->fetch_assoc())
    $vouchers[] = $row;
$voucherResult->free();
$voucherStmt->close();

$rewards = [];
$rwStmt = $conn->prepare(
    "SELECT reward_id AS id, name, description AS `desc`, points_cost AS pts
     FROM loyalty_rewards
     WHERE is_active = 1
     ORDER BY points_cost ASC"
);
$rwStmt->execute();
$rwResult = $rwStmt->get_result();
while ($rw = $rwResult->fetch_assoc()) {
    $rewards[] = $rw;
}
$rwStmt->close();