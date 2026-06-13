<?php
/**
 * includes/admin_notif_helpers.php
 * Pure helper functions for admin_notifications table.
 * No headers, no output — safe to include from any PHP file.
 */

if (!function_exists('upsert_notif')) {
    /**
     * Insert or update a notification row for an admin.
     * Uses ON DUPLICATE KEY so the same ref_id is never duplicated per admin.
     * If the notification was already read, it is NOT resurfaced.
     */
    function upsert_notif(mysqli $conn, int $adminId, string $type, string $refId, string $text, string $path, string $ts): void
    {
        $stmt = $conn->prepare(
            "INSERT INTO admin_notifications (admin_id, type, ref_id, text, path, ts, is_read)
             VALUES (?, ?, ?, ?, ?, ?, 0)
             ON DUPLICATE KEY UPDATE
               text   = IF(is_read = 0, VALUES(text),  text),
               ts     = IF(is_read = 0, VALUES(ts),    ts),
               path   = IF(is_read = 0, VALUES(path),  path)"
        );
        if (!$stmt)
            return;
        $stmt->bind_param('isssss', $adminId, $type, $refId, $text, $path, $ts);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('sync_notifications')) {
    /**
     * Sync live source data into admin_notifications for one admin,
     * then prune rows whose source no longer qualifies.
     */
    function sync_notifications(mysqli $conn, int $adminId): void
    {
        // ── Messages: unread messages addressed to this admin ─────────────────
        $res = mysqli_query(
            $conn,
            "SELECT m.message_id AS id, m.created_at,
                    CONCAT(u.first_name,' ',u.last_name) AS actor
             FROM messages m
             JOIN users u ON u.user_id = m.from_user
             WHERE m.to_user = $adminId AND m.is_read = 0
             ORDER BY m.created_at DESC LIMIT 10"
        );
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            upsert_notif(
                $conn,
                $adminId,
                'message',
                'msg-' . (int) $row['id'],
                'New message from ' . trim((string) ($row['actor'] ?? 'User')),
                'messages.php',
                (string) ($row['created_at'] ?? gmdate('Y-m-d H:i:s'))
            );
        }

        // ── Pending bookings ──────────────────────────────────────────────────
        $res = mysqli_query(
            $conn,
            "SELECT booking_id, created_at FROM bookings
             WHERE status = 'pending'
             ORDER BY created_at DESC LIMIT 10"
        );
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            upsert_notif(
                $conn,
                $adminId,
                'new_booking',
                'booking-' . (int) $row['booking_id'],
                'Pending booking #' . str_pad((string) $row['booking_id'], 4, '0', STR_PAD_LEFT),
                'reservations.php?status=pending',
                (string) ($row['created_at'] ?? gmdate('Y-m-d H:i:s'))
            );
        }

        // ── Open / in-progress maintenance tasks ──────────────────────────────
        $res = mysqli_query(
            $conn,
            "SELECT request_id, issue_description, created_at
             FROM maintenance_requests
             WHERE request_status IN ('open','in_progress')
             ORDER BY created_at DESC LIMIT 10"
        );
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            upsert_notif(
                $conn,
                $adminId,
                'maintenance',
                'task-' . (int) $row['request_id'],
                'Task: ' . trim((string) ($row['issue_description'] ?: 'Maintenance request')),
                'task_summary.php?status=open',
                (string) ($row['created_at'] ?? gmdate('Y-m-d H:i:s'))
            );
        }

        // ── Prune rows whose source no longer qualifies ───────────────────────
        mysqli_query(
            $conn,
            "DELETE an FROM admin_notifications an
             WHERE an.admin_id = $adminId AND an.type = 'message'
               AND NOT EXISTS (
                 SELECT 1 FROM messages m
                 WHERE m.message_id = CAST(SUBSTRING(an.ref_id, 5) AS UNSIGNED)
                   AND m.is_read = 0
               )"
        );
        mysqli_query(
            $conn,
            "DELETE an FROM admin_notifications an
             WHERE an.admin_id = $adminId AND an.type = 'booking'
               AND an.ref_id LIKE 'booking-%'
               AND NOT EXISTS (
                 SELECT 1 FROM bookings b
                 WHERE b.booking_id = CAST(SUBSTRING(an.ref_id, 9) AS UNSIGNED)
                   AND b.status = 'pending'
               )"
        );
        mysqli_query(
            $conn,
            "DELETE an FROM admin_notifications an
             WHERE an.admin_id = $adminId AND an.type = 'task'
               AND NOT EXISTS (
                 SELECT 1 FROM maintenance_requests m
                 WHERE m.request_id = CAST(SUBSTRING(an.ref_id, 6) AS UNSIGNED)
                   AND m.request_status IN ('open','in_progress')
               )"
        );
    }
}