<?php
require_once '../../includes/db.php';

$userId = (int) $_SESSION['user_id'];

// Load or init settings row
$settingsRow = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT * FROM user_settings WHERE user_id=$userId LIMIT 1"
));
if (!$settingsRow) {
    mysqli_query($conn, "INSERT INTO user_settings (user_id) VALUES ($userId)");
    $settingsRow = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT * FROM user_settings WHERE user_id=$userId LIMIT 1"
    ));
}
$s = $settingsRow;
$langCurrent = (string) ($s['language'] ?? 'en');
$activeSessionsCount = max(1, (int) ($s['active_sessions_count'] ?? 2));

// Last login — stored in UTC (see includes/db.php DT_KEYS); fmt_dt() tags it
// with +00:00 so the browser's Date parsing (and the client-side formatting
// in settings.js) renders it in the viewer's own local time, not the
// server's.
$lastLoginRow = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT last_login FROM users WHERE user_id=$userId LIMIT 1"
));
$lastLoginUtc = fmt_dt($lastLoginRow['last_login'] ?? null);