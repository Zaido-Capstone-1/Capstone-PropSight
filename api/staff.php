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
    $roleFilter = trim($_GET['role'] ?? '');
    $validRoles = ['admin', 'manager', 'frontdesk', 'accounting', 'maintenance'];

    $where = "WHERE u.role != 'user'";
    if ($search) {
        $sq = mysqli_real_escape_string($conn, $search);
        $where .= " AND (u.first_name LIKE '%$sq%' OR u.last_name LIKE '%$sq%' OR u.email LIKE '%$sq%')";
    }
    if ($roleFilter && in_array($roleFilter, $validRoles)) {
        $rf = mysqli_real_escape_string($conn, $roleFilter);
        $where .= " AND u.role = '$rf'";
    }

    $res = mysqli_query($conn, "
        SELECT u.user_id, u.first_name, u.last_name, u.email, u.phone,
            u.role, u.is_active, u.last_login, u.created_at, u.profile_photo
        FROM users u $where
        ORDER BY FIELD(u.role,'admin','manager','frontdesk','accounting','maintenance'), u.first_name
    ");
    $staff = [];
    while ($row = mysqli_fetch_assoc($res))
        $staff[] = $row;

    // Counts are always from the full unfiltered set so stat cards stay accurate
    $countRes = mysqli_query($conn, "SELECT role, COUNT(*) as cnt FROM users WHERE role != 'user' GROUP BY role");
    $counts = ['total' => 0, 'admin' => 0, 'manager' => 0, 'frontdesk' => 0, 'accounting' => 0, 'maintenance' => 0];
    while ($cr = mysqli_fetch_assoc($countRes)) {
        $r = strtolower($cr['role']);
        if (isset($counts[$r]))
            $counts[$r] = (int) $cr['cnt'];
        $counts['total'] += (int) $cr['cnt'];
    }

    echo json_encode(['success' => true, 'staff' => $staff, 'counts' => $counts]);
    exit;
}

if ($method === 'POST') {
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    // ── Invite / add new staff ────────────────────────────
    if ($action === 'invite') {
        $first = mysqli_real_escape_string($conn, trim($_POST['first_name'] ?? ''));
        $last = mysqli_real_escape_string($conn, trim($_POST['last_name'] ?? ''));
        $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
        $phone = mysqli_real_escape_string($conn, trim($_POST['phone'] ?? ''));
        $role = in_array($_POST['role'] ?? '', ['admin', 'manager', 'frontdesk', 'accounting', 'maintenance'])
            ? $_POST['role'] : 'frontdesk';

        if (!$first || !$last || !$email) {
            echo json_encode(['success' => false, 'message' => 'Name and email required.']);
            exit;
        }
        if (!filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
            exit;
        }

        $chk = mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT user_id FROM users WHERE email='$email' LIMIT 1"
        ));
        if ($chk) {
            echo json_encode(['success' => false, 'message' => 'Email already in use.']);
            exit;
        }

        // Generate secure temp password
        $tempPass = 'PS@' . bin2hex(random_bytes(4));
        $hash = password_hash($tempPass, PASSWORD_BCRYPT);
        $hashEsc = mysqli_real_escape_string($conn, $hash);

        $sql = "INSERT INTO users (first_name, last_name, email, phone, password, role, is_active)
                VALUES ('$first','$last','$email','$phone','$hashEsc','$role',1)";

        if (!mysqli_query($conn, $sql)) {
            echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
            exit;
        }

        $newId = mysqli_insert_id($conn);

        // Send invite email
        require_once __DIR__ . '/../includes/email_service.php';

        $firstName = htmlspecialchars($first, ENT_QUOTES, 'UTF-8');
        $siteName = htmlspecialchars(MAIL_FROM_NAME, ENT_QUOTES, 'UTF-8');
        $roleLabels = ['admin' => 'Admin', 'manager' => 'Property Manager', 'frontdesk' => 'Front Desk', 'accounting' => 'Accounting', 'maintenance' => 'Maintenance'];
        $roleLabel = $roleLabels[$role] ?? ucfirst($role);
        $loginUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
            . '://' . $_SERVER['HTTP_HOST']
            . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/') . '/index.php#login';

        $htmlBody = <<<HTML
        <div style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;">
            <tr><td align="center">
            <table role="presentation" width="520" cellpadding="0" cellspacing="0"
                    style="background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5e7eb;">
                <tr>
                <td style="background:linear-gradient(135deg,#1e3a5f 0%,#2d5a8e 100%);padding:32px 40px;text-align:center;">
                    <div style="color:#ffffff;font-size:22px;font-weight:700;letter-spacing:-0.3px;">$siteName</div>
                    <div style="color:rgba(255,255,255,0.75);font-size:13px;margin-top:4px;">You've been invited to join the team</div>
                </td>
                </tr>
                <tr>
                <td style="padding:36px 40px;">
                    <p style="font-size:15px;color:#374151;margin:0 0 18px;">Hi <strong>$firstName</strong>,</p>
                    <p style="font-size:14px;color:#6b7280;line-height:1.7;margin:0 0 24px;">
                    You've been added to <strong>$siteName</strong> as a <strong>$roleLabel</strong>.
                    Use the credentials below to log in and get started.
                    </p>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                        style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:20px 24px;margin-bottom:28px;">
                    <tr>
                        <td style="font-size:13px;color:#64748b;padding-bottom:10px;">
                        <strong style="color:#1e293b;">Email:</strong>&nbsp; {$_POST['email']}
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size:13px;color:#64748b;">
                        <strong style="color:#1e293b;">Temporary Password:</strong>&nbsp;
                        <span style="font-family:monospace;background:#e0f2fe;color:#0369a1;padding:2px 8px;border-radius:4px;">$tempPass</span>
                        </td>
                    </tr>
                    </table>
                    <div style="text-align:center;margin-bottom:28px;">
                    <a href="$loginUrl"
                        style="display:inline-block;background:#1e3a5f;color:#ffffff;text-decoration:none;
                                padding:14px 36px;border-radius:8px;font-size:15px;font-weight:600;">
                        Log In Now
                    </a>
                    </div>
                    <p style="font-size:12px;color:#94a3b8;text-align:center;margin:0;">
                    Please change your password after your first login.
                    </p>
                </td>
                </tr>
                <tr>
                <td style="background:#f8fafc;padding:18px 40px;text-align:center;border-top:1px solid #e5e7eb;">
                    <p style="font-size:12px;color:#94a3b8;margin:0;">
                    &copy; <?= date('Y') ?> $siteName. If you did not expect this invitation, please ignore this email.
                    </p>
                </td>
                </tr>
            </table>
            </td></tr>
        </table>
        </div>
        HTML;

        $textBody = "Hi $firstName,\n\nYou've been added to $siteName as a $roleLabel.\n\nEmail: {$_POST['email']}\nTemporary Password: $tempPass\n\nLog in at: $loginUrl\n\nPlease change your password after your first login.";

        $emailSent = $emailService->sendEmail(
            $_POST['email'],
            "You're invited to join $siteName",
            $htmlBody,
            $textBody
        );

        if ($emailSent) {
            echo json_encode([
                'success' => true,
                'message' => "Invite sent to {$_POST['email']}.",
                'user_id' => $newId,
            ]);
        } else {
            // Account created but email failed — still success, warn admin
            echo json_encode([
                'success' => true,
                'message' => "Account created but email could not be sent. Temp password: $tempPass",
                'user_id' => $newId,
            ]);
        }
        exit;
    }

    // ── Update role ───────────────────────────────────────
    if ($action === 'update_role') {
        $uid = (int) ($_POST['user_id'] ?? 0);
        $role = in_array($_POST['role'] ?? '', ['admin', 'manager', 'frontdesk', 'accounting', 'maintenance'])
            ? $_POST['role'] : null;
        if (!$uid || !$role) {
            echo json_encode(['success' => false, 'message' => 'Invalid.']);
            exit;
        }
        if (mysqli_query($conn, "UPDATE users SET role='$role' WHERE user_id=$uid")) {
            echo json_encode(['success' => true, 'message' => 'Role updated.']);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
        }
        exit;
    }

    // ── Deactivate / reactivate ───────────────────────────
    if ($action === 'toggle_active') {
        $uid = (int) ($_POST['user_id'] ?? 0);

        // Prevent self-deactivation
        if ($uid === (int) $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'You cannot deactivate your own account.']);
            exit;
        }
        $curRes = mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT is_active FROM users WHERE user_id=$uid LIMIT 1"
        ));
        if (!$curRes) {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            exit;
        }
        $newVal = $curRes['is_active'] ? 0 : 1;
        if (mysqli_query($conn, "UPDATE users SET is_active=$newVal WHERE user_id=$uid")) {
            echo json_encode(['success' => true, 'message' => $newVal ? 'Account reactivated.' : 'Account deactivated.', 'is_active' => $newVal]);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
        }
        exit;
    }

    // ── Reset password ────────────────────────────────────
    if ($action === 'reset_password') {
        $uid = (int) ($_POST['user_id'] ?? 0);
        $tempPass = 'Propsight@' . rand(1000, 9999);
        $hash = password_hash($tempPass, PASSWORD_BCRYPT);
        $hashEsc = mysqli_real_escape_string($conn, $hash);
        if (mysqli_query($conn, "UPDATE users SET password='$hashEsc' WHERE user_id=$uid")) {
            echo json_encode(['success' => true, 'message' => "Password reset. New temp password: $tempPass", 'temp_pass' => $tempPass]);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
        }
        exit;
    }

    // ── Remove staff ──────────────────────────────────────
    if ($action === 'remove_staff') {
        $uid = (int) ($_POST['user_id'] ?? 0);
        if (!$uid) {
            echo json_encode(['success' => false, 'message' => 'Invalid user.']);
            exit;
        }

        // Prevent self-deletion
        if ($uid === (int) $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'You cannot remove your own account.']);
            exit;
        }

        // Confirm user exists and is staff
        $chk = mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT user_id FROM users WHERE user_id=$uid AND role != 'user' LIMIT 1"
        ));
        if (!$chk) {
            echo json_encode(['success' => false, 'message' => 'Staff member not found.']);
            exit;
        }

        if (mysqli_query($conn, "DELETE FROM users WHERE user_id=$uid")) {
            echo json_encode(['success' => true, 'message' => 'Staff member removed.']);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}