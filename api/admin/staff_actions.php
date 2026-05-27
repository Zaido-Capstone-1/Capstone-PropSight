<?php
include '../../includes/session.php';
include '../../includes/db.php';

header('Content-Type: application/json');

// Only admins
if (($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

// CSRF check
$csrf = $_POST['csrf_token'] ?? '';
if (!$csrf || $csrf !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
    exit;
}

$action = trim($_POST['action'] ?? '');
$userId = (int) ($_POST['user_id'] ?? 0);
$adminId = (int) $_SESSION['user_id'];

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
    exit;
}

// ── toggle_active ──────────────────────────────────────────────────────────
if ($action === 'toggle_active') {
    // Prevent admin from deactivating themselves
    if ($userId === $adminId) {
        echo json_encode(['success' => false, 'message' => 'You cannot deactivate your own account.']);
        exit;
    }

    // Fetch current state
    $stmt = $conn->prepare("SELECT is_active, role FROM users WHERE user_id=? AND role != 'user' LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Staff member not found.']);
        exit;
    }

    $newState = (int) $row['is_active'] === 1 ? 0 : 1;

    $stmt = $conn->prepare("UPDATE users SET is_active=? WHERE user_id=?");
    $stmt->bind_param('ii', $newState, $userId);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'new_state' => $newState,
        'message' => $newState ? 'Staff member activated.' : 'Staff member deactivated.',
    ]);
    exit;
}

// ── remove_staff ───────────────────────────────────────────────────────────
if ($action === 'remove_staff') {
    if ($userId === $adminId) {
        echo json_encode(['success' => false, 'message' => 'You cannot remove your own account.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT role FROM users WHERE user_id=? AND role != 'user' LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Staff member not found.']);
        exit;
    }

    // Soft-delete: set role to 'user' and deactivate rather than hard DELETE
    $stmt = $conn->prepare("UPDATE users SET role='user', is_active=0 WHERE user_id=?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Staff member removed.']);
    exit;
}

// ── invite_staff ───────────────────────────────────────────────────────────
if ($action === 'invite_staff') {
    $email = trim($_POST['email'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $role = trim($_POST['role'] ?? '');

    $allowedRoles = ['manager', 'frontdesk', 'accounting', 'maintenance'];

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
        exit;
    }
    if (!$firstName || !$lastName) {
        echo json_encode(['success' => false, 'message' => 'First and last name are required.']);
        exit;
    }
    if (!in_array($role, $allowedRoles, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid role selected.']);
        exit;
    }

    // Check if email already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        echo json_encode(['success' => false, 'message' => 'A user with that email already exists.']);
        exit;
    }

    // Generate a temporary password
    $tempPass = bin2hex(random_bytes(6));
    $hashed = password_hash($tempPass, PASSWORD_DEFAULT);

    $stmt = $conn->prepare(
        "INSERT INTO users (first_name, last_name, email, password, role, is_active, created_at)
         VALUES (?, ?, ?, ?, ?, 1, NOW())"
    );
    $stmt->bind_param('sssss', $firstName, $lastName, $email, $hashed, $role);
    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => "Staff member invited. Temp password: $tempPass",
        'user_id' => $newId,
        'temp_pass' => $tempPass,
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);