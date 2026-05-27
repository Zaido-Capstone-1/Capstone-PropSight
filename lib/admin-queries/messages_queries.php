<?php
/**
 * lib/admin/messages_data.php
 * Data layer for pages/admin/messages.php
 * Requires: $conn (mysqli), $_SESSION['user_id']
 */

$adminId = (int) $_SESSION['user_id'];

// Message threads — subqueries reference $adminId; use prepared statement
$stmt = $conn->prepare(
    "SELECT DISTINCT
        IF(m.from_user=?, m.to_user, m.from_user) AS other_id,
        CONCAT(u.first_name,' ',u.last_name)       AS other_name,
        u.email                                     AS other_email,
        (SELECT body FROM messages
         WHERE (from_user=? AND to_user=IF(m.from_user=?, m.to_user, m.from_user))
            OR (from_user=IF(m.from_user=?, m.to_user, m.from_user) AND to_user=?)
         ORDER BY created_at DESC LIMIT 1)          AS last_body,
        (SELECT created_at FROM messages
         WHERE (from_user=? AND to_user=IF(m.from_user=?, m.to_user, m.from_user))
            OR (from_user=IF(m.from_user=?, m.to_user, m.from_user) AND to_user=?)
         ORDER BY created_at DESC LIMIT 1)          AS last_time,
        (SELECT COUNT(*) FROM messages
         WHERE from_user=IF(m.from_user=?, m.to_user, m.from_user)
           AND to_user=? AND is_read=0)             AS unread
     FROM messages m
     JOIN users u ON u.user_id = IF(m.from_user=?, m.to_user, m.from_user)
     WHERE m.from_user=? OR m.to_user=?
     ORDER BY last_time DESC"
);

// 14 ? placeholders, all integers
$stmt->bind_param(
    'iiiiiiiiiiiiii',
    $adminId,
    $adminId,
    $adminId,
    $adminId,
    $adminId,
    $adminId,
    $adminId,
    $adminId,
    $adminId,
    $adminId,
    $adminId,
    $adminId,
    $adminId,
    $adminId
);
$stmt->execute();
$threads = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Active users for "New Message" dropdown (no user input)
$usersRes = $conn->query(
    "SELECT user_id, first_name, last_name, email
     FROM users WHERE role='user' AND is_active=1 ORDER BY first_name"
);
$userList = $usersRes->fetch_all(MYSQLI_ASSOC);