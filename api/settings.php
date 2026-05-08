<?php
/**
 * API: /api/settings.php
 * GET  — return current admin profile + system settings
 * POST — update profile, password, system settings
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$adminId = (int) $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $user = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT user_id, first_name, last_name, email, phone, address, role, last_login
         FROM users WHERE user_id=$adminId LIMIT 1"
    ));

    // System settings
    $sRes = mysqli_query($conn, "SELECT setting_key, value FROM admin_settings");
    $settings = [];
    while ($row = mysqli_fetch_assoc($sRes)) {
        $settings[$row['setting_key']] = $row['value'];
    }

    echo json_encode(['success' => true, 'user' => $user, 'settings' => $settings]);
    exit;
}

if ($method === 'POST') {
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    // ── Update profile ────────────────────────────────────
    if ($action === 'update_profile') {
        $first = mysqli_real_escape_string($conn, trim($_POST['first_name'] ?? ''));
        $last = mysqli_real_escape_string($conn, trim($_POST['last_name'] ?? ''));
        $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
        $phone = mysqli_real_escape_string($conn, trim($_POST['phone'] ?? ''));
        $address = mysqli_real_escape_string($conn, trim($_POST['address'] ?? ''));

        if (!$first || !$last || !$email) {
            echo json_encode(['success' => false, 'message' => 'Name and email are required.']);
            exit;
        }

        // Check email uniqueness
        $chk = mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT user_id FROM users WHERE email='$email' AND user_id!=$adminId LIMIT 1"
        ));
        if ($chk) {
            echo json_encode(['success' => false, 'message' => 'Email already in use.']);
            exit;
        }

        $sql = "UPDATE users SET first_name='$first', last_name='$last',
                email='$email', phone='$phone', address='$address'
                WHERE user_id=$adminId";

        if (mysqli_query($conn, $sql)) {
            $_SESSION['first_name'] = $first;
            $_SESSION['last_name'] = $last;
            $_SESSION['email'] = $email;
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
        }
        exit;
    }

    // ── Change password ───────────────────────────────────
    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (strlen($new) < 8) {
            echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters.']);
            exit;
        }
        if ($new !== $confirm) {
            echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
            exit;
        }

        $userRow = mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT password FROM users WHERE user_id=$adminId LIMIT 1"
        ));

        if (!password_verify($current, $userRow['password'])) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
            exit;
        }

        $hash = password_hash($new, PASSWORD_BCRYPT);
        $hashEsc = mysqli_real_escape_string($conn, $hash);

        if (mysqli_query($conn, "UPDATE users SET password='$hashEsc' WHERE user_id=$adminId")) {
            echo json_encode(['success' => true, 'message' => 'Password updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
        }
        exit;
    }

    // ── Toggle 2FA ────────────────────────────────────────
    if ($action === 'toggle_2fa') {
        $new2fa = isset($_POST['enabled']) ? (($_POST['enabled'] === '1') ? 1 : 0) : null;
        if ($new2fa === null) {
            $cur = mysqli_fetch_assoc(mysqli_query($conn, "SELECT two_factor_enabled FROM user_settings WHERE user_id=$adminId LIMIT 1"));
            $new2fa = empty($cur['two_factor_enabled']) ? 1 : 0;
        }
        // Ensure row exists
        mysqli_query($conn, "INSERT INTO user_settings (user_id) VALUES ($adminId) ON DUPLICATE KEY UPDATE user_id=user_id");
        // Check column exists
        $colCheck = mysqli_fetch_assoc(mysqli_query($conn, "SHOW COLUMNS FROM user_settings LIKE 'two_factor_enabled'"));
        if ($colCheck) {
            mysqli_query($conn, "UPDATE user_settings SET two_factor_enabled=$new2fa WHERE user_id=$adminId");
        }
        echo json_encode([
            'success' => true,
            'enabled' => (bool) $new2fa,
            'message' => $new2fa ? 'Two-factor authentication enabled.' : 'Two-factor authentication disabled.',
        ]);
        exit;
    }

    // ── Update system settings ────────────────────────────
    if ($action === 'update_system') {
        $allowed = [
            'site_name',
            'site_email',
            'currency',
            'currency_symbol',
            'checkout_time',
            'checkin_time',
            'min_nights',
            'max_nights',
            'loyalty_points_per_peso',
            'booking_cancellation_hours',
            'tax_rate',
        ];
        $updated = 0;
        foreach ($allowed as $key) {
            if (isset($_POST[$key])) {
                $val = mysqli_real_escape_string($conn, trim($_POST[$key]));
                $keyEsc = mysqli_real_escape_string($conn, $key);
                $by = $adminId;
                mysqli_query(
                    $conn,
                    "INSERT INTO admin_settings (setting_key,value,updated_by) VALUES ('$keyEsc','$val',$by)
                     ON DUPLICATE KEY UPDATE value='$val', updated_by=$by"
                );
                $updated++;
            }
        }
        echo json_encode(['success' => true, 'message' => "$updated setting(s) saved."]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}