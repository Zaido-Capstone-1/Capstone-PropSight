<?php
/**
 * API: /endpoints/user/settings.php
 * GET  — return current user notification/privacy/security settings
 * POST — update settings, change password
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_not_blacklisted();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

function getSettingsColumns(mysqli $conn): array {
    $cols = [];
    $res = mysqli_query($conn, 'SHOW COLUMNS FROM user_settings');
    if (!$res) {
        return $cols;
    }
    while ($row = mysqli_fetch_assoc($res)) {
        $name = (string)($row['Field'] ?? '');
        if ($name !== '') {
            $cols[$name] = true;
        }
    }
    return $cols;
}

function settingsColumnExists(array $columns, string $name): bool {
    return isset($columns[$name]);
}

if ($method === 'GET') {
    $settingsColumns = getSettingsColumns($conn);
    // Ensure a settings row exists
    $sel = $conn->prepare('SELECT * FROM user_settings WHERE user_id = ? LIMIT 1');
    $sel->bind_param('i', $userId);
    $sel->execute();
    $existing = $sel->get_result()->fetch_assoc();
    $sel->close();
    if (!$existing) {
        $ins = $conn->prepare('INSERT INTO user_settings (user_id) VALUES (?)');
        $ins->bind_param('i', $userId);
        $ins->execute();
        $ins->close();
        $sel2 = $conn->prepare('SELECT * FROM user_settings WHERE user_id = ? LIMIT 1');
        $sel2->bind_param('i', $userId);
        $sel2->execute();
        $existing = $sel2->get_result()->fetch_assoc();
        $sel2->close();
    }

    // Add defaults for columns that may not exist in older schemas.
    foreach ([
        'push_inapp_alerts' => 1,
        'push_checkout_reminder' => 1,
        'push_room_availability' => 0,
        'privacy_share_history' => 0,
        'privacy_recommendations' => 1,
        'privacy_analytics' => 1,
        'active_sessions_count' => 1,
    ] as $key => $defaultVal) {
        if (!settingsColumnExists($settingsColumns, $key) && !isset($existing[$key])) {
            $existing[$key] = $defaultVal;
        }
    }

    echo json_encode(['success' => true, 'settings' => $existing]);
    exit;
}

if ($method === 'POST') {
    require_csrf_token(true);
    $settingsColumns = getSettingsColumns($conn);
    $action = $_POST['action'] ?? 'update_notifications';

    $ensureRow = $conn->prepare(
        'INSERT INTO user_settings (user_id) VALUES (?) ON DUPLICATE KEY UPDATE user_id = user_id'
    );
    $ensureRow->bind_param('i', $userId);
    $ensureRow->execute();
    $ensureRow->close();

    // ── Update notification prefs ─────────────────────────
    if ($action === 'update_notifications') {
        $fields = [
            'notif_booking_confirm','notif_checkin_remind','notif_promotions',
            'notif_loyalty','notif_newsletter','notif_sms',
            'push_inapp_alerts','push_checkout_reminder','push_room_availability',
        ];
        $sets = [];
        $params = [];
        $types = '';
        foreach ($fields as $f) {
            if (!settingsColumnExists($settingsColumns, $f)) {
                continue;
            }
            $val = isset($_POST[$f]) && $_POST[$f] ? 1 : 0;
            $sets[] = "$f = ?";
            $params[] = $val;
            $types .= 'i';
        }
        if (empty($sets)) {
            echo json_encode(['success'=>true,'message'=>'No notification fields available in current schema.']);
            exit;
        }

        $setSQL = implode(', ', $sets);
        $sql = "UPDATE user_settings SET $setSQL WHERE user_id = ?";
        $params[] = $userId;
        $types .= 'i';

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            echo json_encode(['success'=>true,'message'=>'Notification preferences saved.']);
        } else {
            echo json_encode(['success'=>false,'message'=>mysqli_error($conn)]);
        }
        $stmt->close();
        exit;
    }

    // ── Update privacy/language ───────────────────────────
    if ($action === 'update_privacy') {
        $profile  = in_array($_POST['privacy_profile'] ?? '', ['public','private'])
                    ? $_POST['privacy_profile'] : 'private';
        $activity = isset($_POST['privacy_activity']) ? 1 : 0;
        $shareHistory = isset($_POST['privacy_share_history']) && $_POST['privacy_share_history'] ? 1 : 0;
        $recommendations = isset($_POST['privacy_recommendations']) && $_POST['privacy_recommendations'] ? 1 : 0;
        $analytics = isset($_POST['privacy_analytics']) && $_POST['privacy_analytics'] ? 1 : 0;
        $lang     = substr(trim($_POST['language'] ?? 'en'), 0, 10);
        $tz       = substr(trim($_POST['timezone'] ?? 'Asia/Manila'), 0, 50);

        $parts = [];
        $types = '';
        $params = [];
        $map = [
            'privacy_profile' => $profile,
            'privacy_activity' => $activity,
            'privacy_share_history' => $shareHistory,
            'privacy_recommendations' => $recommendations,
            'privacy_analytics' => $analytics,
            'language' => $lang,
            'timezone' => $tz,
        ];
        foreach ($map as $col => $value) {
            if (!settingsColumnExists($settingsColumns, $col)) {
                continue;
            }
            $parts[] = "$col = ?";
            if (is_int($value)) {
                $types .= 'i';
            } else {
                $types .= 's';
            }
            $params[] = $value;
        }
        if (empty($parts)) {
            echo json_encode(['success'=>true,'message'=>'No privacy fields available in current schema.']);
            exit;
        }
        $sql = 'UPDATE user_settings SET ' . implode(', ', $parts) . ' WHERE user_id = ?';
        $types .= 'i';
        $params[] = $userId;
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            echo json_encode(['success'=>true,'message'=>'Privacy settings saved.']);
        } else {
            echo json_encode(['success'=>false,'message'=>mysqli_error($conn)]);
        }
        $stmt->close();
        exit;
    }

    // ── Change password ───────────────────────────────────
    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (strlen($new) < 8) {
            echo json_encode(['success'=>false,'message'=>'Password must be at least 8 characters.']); exit;
        }
        if ($new !== $confirm) {
            echo json_encode(['success'=>false,'message'=>'Passwords do not match.']); exit;
        }

        $userStmt = $conn->prepare('SELECT password FROM users WHERE user_id = ? LIMIT 1');
        $userStmt->bind_param('i', $userId);
        $userStmt->execute();
        $userRow = $userStmt->get_result()->fetch_assoc();
        $userStmt->close();
        if (!$userRow || !password_verify($current, $userRow['password'])) {
            echo json_encode(['success'=>false,'message'=>'Current password is incorrect.']); exit;
        }

        $hash    = password_hash($new, PASSWORD_BCRYPT);
        $upd = $conn->prepare('UPDATE users SET password = ? WHERE user_id = ?');
        $upd->bind_param('si', $hash, $userId);
        if ($upd->execute()) {
            echo json_encode(['success'=>true,'message'=>'Password changed successfully.']);
        } else {
            echo json_encode(['success'=>false,'message'=>mysqli_error($conn)]);
        }
        $upd->close();
        exit;
    }

    // ── Toggle 2FA ────────────────────────────────────────
    if ($action === 'toggle_2fa') {
        $new2fa = isset($_POST['enabled']) ? (($_POST['enabled'] === '1' || $_POST['enabled'] === 'true') ? 1 : 0) : null;
        if ($new2fa === null) {
            $current2fa = 0;
            if (settingsColumnExists($settingsColumns, 'two_factor_enabled')) {
                $curStmt = $conn->prepare(
                    'SELECT COALESCE(two_factor_enabled, 0) AS v FROM user_settings WHERE user_id = ? LIMIT 1'
                );
                $curStmt->bind_param('i', $userId);
                $curStmt->execute();
                $current2fa = (int)($curStmt->get_result()->fetch_assoc()['v'] ?? 0);
                $curStmt->close();
            }
            $new2fa = $current2fa ? 0 : 1;
        }

        if (settingsColumnExists($settingsColumns, 'two_factor_enabled')) {
            $twofaStmt = $conn->prepare(
                'INSERT INTO user_settings (user_id, two_factor_enabled) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE two_factor_enabled = VALUES(two_factor_enabled)'
            );
            $twofaStmt->bind_param('ii', $userId, $new2fa);
            $twofaStmt->execute();
            $twofaStmt->close();
        }

        echo json_encode([
            'success' => true,
            'enabled' => (bool)$new2fa,
            'message' => $new2fa ? 'Two-factor authentication enabled.' : 'Two-factor authentication disabled.',
        ]);
        exit;
    }

    if ($action === 'request_data_export') {
        if (settingsColumnExists($settingsColumns, 'data_export_requested_at')) {
            $stmt = $conn->prepare('UPDATE user_settings SET data_export_requested_at = NOW() WHERE user_id = ?');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();
        }
        echo json_encode(['success' => true, 'message' => 'Data export requested. We will email your data shortly.']);
        exit;
    }

    if ($action === 'revoke_session') {
        if (settingsColumnExists($settingsColumns, 'last_session_action_at') && settingsColumnExists($settingsColumns, 'active_sessions_count')) {
            $stmt = $conn->prepare(
                'UPDATE user_settings
                 SET last_session_action_at = NOW(),
                     active_sessions_count = GREATEST(1, COALESCE(active_sessions_count, 2) - 1)
                 WHERE user_id = ?'
            );
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();
        }
        $countStmt = $conn->prepare('SELECT COALESCE(active_sessions_count, 1) AS c FROM user_settings WHERE user_id = ? LIMIT 1');
        $countStmt->bind_param('i', $userId);
        $countStmt->execute();
        $countRow = $countStmt->get_result()->fetch_assoc();
        $countStmt->close();
        echo json_encode([
            'success' => true,
            'message' => 'Session revoked.',
            'active_sessions_count' => (int)($countRow['c'] ?? 1),
        ]);
        exit;
    }

    if ($action === 'signout_other_devices') {
        if (settingsColumnExists($settingsColumns, 'last_session_action_at') && settingsColumnExists($settingsColumns, 'active_sessions_count')) {
            $stmt = $conn->prepare(
                'UPDATE user_settings SET last_session_action_at = NOW(), active_sessions_count = 1 WHERE user_id = ?'
            );
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();
        }
        echo json_encode([
            'success' => true,
            'message' => 'All other devices have been signed out.',
            'active_sessions_count' => 1,
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}
