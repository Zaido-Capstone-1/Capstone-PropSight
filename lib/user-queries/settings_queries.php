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