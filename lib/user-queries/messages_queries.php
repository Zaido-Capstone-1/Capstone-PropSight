<?php
require_once '../../includes/db.php';

$userId = (int) $_SESSION['user_id'];

// Preload threads for fast first render
$threadsRes = mysqli_query($conn, "
    SELECT
        IF(m.from_user=$userId, m.to_user, m.from_user) AS admin_id,
        CONCAT(u.first_name,' ',u.last_name)            AS admin_name,
        (SELECT body FROM messages
         WHERE (from_user=$userId AND to_user=IF(m.from_user=$userId,m.to_user,m.from_user))
            OR (from_user=IF(m.from_user=$userId,m.to_user,m.from_user) AND to_user=$userId)
         ORDER BY created_at DESC LIMIT 1) AS last_body,
        (SELECT created_at FROM messages
         WHERE (from_user=$userId AND to_user=IF(m.from_user=$userId,m.to_user,m.from_user))
            OR (from_user=IF(m.from_user=$userId,m.to_user,m.from_user) AND to_user=$userId)
         ORDER BY created_at DESC LIMIT 1) AS last_time,
        (SELECT COUNT(*) FROM messages
         WHERE from_user=IF(m.from_user=$userId,m.to_user,m.from_user)
           AND to_user=$userId AND is_read=0) AS unread
    FROM messages m
    JOIN users u ON u.user_id = IF(m.from_user=$userId, m.to_user, m.from_user)
    WHERE (m.from_user=$userId OR m.to_user=$userId)
    GROUP BY admin_id
    ORDER BY last_time DESC
");
$threads = [];
while ($t = mysqli_fetch_assoc($threadsRes))
    $threads[] = $t;

$adminRes = mysqli_query(
    $conn,
    "SELECT user_id, first_name, last_name, email FROM users WHERE role='admin' AND is_active=1 ORDER BY first_name"
);
$admins = [];
while ($a = mysqli_fetch_assoc($adminRes))
    $admins[] = $a;