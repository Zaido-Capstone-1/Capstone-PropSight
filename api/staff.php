<?php
/**
 * API: /api/staff.php
 * GET  — list staff members
 * POST — add / update / deactivate / reactivate staff
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $search = trim($_GET['search'] ?? '');
    $where  = "WHERE u.role != 'user'";
    if ($search) {
        $sq = mysqli_real_escape_string($conn, $search);
        $where .= " AND (u.first_name LIKE '%$sq%' OR u.last_name LIKE '%$sq%' OR u.email LIKE '%$sq%')";
    }

    $res = mysqli_query($conn, "
        SELECT u.user_id, u.first_name, u.last_name, u.email, u.phone,
               u.role, u.is_active, u.last_login, u.created_at
        FROM users u $where
        ORDER BY FIELD(u.role,'admin','manager','frontdesk','accounting','maintenance'), u.first_name
    ");
    $staff = [];
    while ($row = mysqli_fetch_assoc($res)) $staff[] = $row;

    $counts = ['total' => 0, 'admin' => 0, 'manager' => 0, 'frontdesk' => 0, 'accounting' => 0, 'maintenance' => 0];
    foreach ($staff as $s) {
        $counts['total']++;
        $r = strtolower($s['role']);
        if (isset($counts[$r])) $counts[$r]++;
    }

    echo json_encode(['success' => true, 'staff' => $staff, 'counts' => $counts]);
    exit;
}

if ($method === 'POST') {
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    // ── Invite / add new staff ────────────────────────────
    if ($action === 'invite') {
        $first   = mysqli_real_escape_string($conn, trim($_POST['first_name'] ?? ''));
        $last    = mysqli_real_escape_string($conn, trim($_POST['last_name']  ?? ''));
        $email   = mysqli_real_escape_string($conn, trim($_POST['email']      ?? ''));
        $phone   = mysqli_real_escape_string($conn, trim($_POST['phone']      ?? ''));
        $role    = in_array($_POST['role'] ?? '', ['admin','manager','frontdesk','accounting','maintenance'])
                   ? $_POST['role'] : 'frontdesk';

        if (!$first || !$last || !$email) {
            echo json_encode(['success'=>false,'message'=>'Name and email required.']); exit;
        }

        $chk = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT user_id FROM users WHERE email='$email' LIMIT 1"));
        if ($chk) { echo json_encode(['success'=>false,'message'=>'Email already in use.']); exit; }

        // Temp password - in production would send email
        $tempPass = 'Propsight@' . rand(1000,9999);
        $hash     = password_hash($tempPass, PASSWORD_BCRYPT);
        $hashEsc  = mysqli_real_escape_string($conn, $hash);

        $sql = "INSERT INTO users (first_name, last_name, email, phone, password, role, is_active)
                VALUES ('$first','$last','$email','$phone','$hashEsc','$role',1)";

        if (mysqli_query($conn, $sql)) {
            $newId = mysqli_insert_id($conn);
            echo json_encode([
                'success'  => true,
                'message'  => "Staff member added. Temp password: $tempPass",
                'user_id'  => $newId,
                'temp_pass'=> $tempPass,
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
        }
        exit;
    }

    // ── Update role ───────────────────────────────────────
    if ($action === 'update_role') {
        $uid  = (int)($_POST['user_id'] ?? 0);
        $role = in_array($_POST['role'] ?? '', ['admin','manager','frontdesk','accounting','maintenance'])
                ? $_POST['role'] : null;
        if (!$uid || !$role) { echo json_encode(['success'=>false,'message'=>'Invalid.']); exit; }
        if (mysqli_query($conn, "UPDATE users SET role='$role' WHERE user_id=$uid")) {
            echo json_encode(['success'=>true,'message'=>'Role updated.']);
        } else {
            echo json_encode(['success'=>false,'message'=>mysqli_error($conn)]);
        }
        exit;
    }

    // ── Deactivate / reactivate ───────────────────────────
    if ($action === 'toggle_active') {
        $uid   = (int)($_POST['user_id'] ?? 0);
        $curRes = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT is_active FROM users WHERE user_id=$uid LIMIT 1"));
        if (!$curRes) { echo json_encode(['success'=>false,'message'=>'User not found.']); exit; }
        $newVal = $curRes['is_active'] ? 0 : 1;
        if (mysqli_query($conn, "UPDATE users SET is_active=$newVal WHERE user_id=$uid")) {
            echo json_encode(['success'=>true,'message'=>$newVal ? 'Account reactivated.' : 'Account deactivated.','is_active'=>$newVal]);
        } else {
            echo json_encode(['success'=>false,'message'=>mysqli_error($conn)]);
        }
        exit;
    }

    // ── Reset password ────────────────────────────────────
    if ($action === 'reset_password') {
        $uid      = (int)($_POST['user_id'] ?? 0);
        $tempPass = 'Propsight@' . rand(1000,9999);
        $hash     = password_hash($tempPass, PASSWORD_BCRYPT);
        $hashEsc  = mysqli_real_escape_string($conn, $hash);
        if (mysqli_query($conn, "UPDATE users SET password='$hashEsc' WHERE user_id=$uid")) {
            echo json_encode(['success'=>true,'message'=>"Password reset. New temp password: $tempPass",'temp_pass'=>$tempPass]);
        } else {
            echo json_encode(['success'=>false,'message'=>mysqli_error($conn)]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}
