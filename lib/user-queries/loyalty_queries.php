<?php
require_once '../../includes/db.php';

$userId = (int) $_SESSION['user_id'];

// Auto-heal old data: award missing points for completed bookings.
$syncRes = mysqli_query($conn, "
    SELECT b.booking_id, b.total_amount
    FROM bookings b
    LEFT JOIN loyalty_points lp
           ON lp.booking_id = b.booking_id
          AND lp.user_id = b.user_id
          AND lp.type = 'earn'
    WHERE b.user_id = $userId
      AND b.status = 'completed'
      AND lp.id IS NULL
");
while ($syncRes && ($sr = mysqli_fetch_assoc($syncRes))) {
    $syncBookingId = (int) ($sr['booking_id'] ?? 0);
    $syncAmount = (float) ($sr['total_amount'] ?? 0);
    if ($syncBookingId <= 0)
        continue;
    $syncPts = max(1, (int) floor($syncAmount / 10));
    $syncDesc = mysqli_real_escape_string($conn, "Booking #$syncBookingId stay completed");
    mysqli_query($conn, "
        INSERT INTO loyalty_points (user_id, points, type, description, booking_id)
        VALUES ($userId, $syncPts, 'earn', '$syncDesc', $syncBookingId)
    ");
}

$balRow = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COALESCE(SUM(points),0) AS bal FROM loyalty_points WHERE user_id=$userId"
));
$points = max(0, (int) $balRow['bal']);

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
$progress_pct = $next_td ? min(100, round((($points - $tier_base) / ($tier_total - $tier_base)) * 100)) : 100;

$histRes = mysqli_query(
    $conn,
    "SELECT points, type, description, created_at FROM loyalty_points WHERE user_id=$userId ORDER BY created_at DESC LIMIT 30"
);
$history = [];
while ($row = mysqli_fetch_assoc($histRes)) {
    $history[] = [
        'date' => date('M j, Y', strtotime($row['created_at'])),
        'desc' => $row['description'],
        'pts' => ($row['points'] >= 0 ? '+' : '') . number_format($row['points']),
        'type' => $row['type'],
    ];
}
if (empty($history)) {
    $history[] = ['date' => date('M j, Y'), 'desc' => 'Welcome! Start booking to earn points.', 'pts' => '+0', 'type' => 'bonus'];
}