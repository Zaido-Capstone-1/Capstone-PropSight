<?php
/**
 * API: /api/user/payment_methods.php
 * GET  — list saved payment methods + billing history
 * POST — add_card / add_ewallet / remove / set_default
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

$userId = (int) $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// ── GET ───────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $res = mysqli_query(
        $conn,
        "SELECT * FROM payment_methods
         WHERE user_id=$userId AND is_active=1
         ORDER BY is_default DESC, created_at ASC"
    );
    $methods = [];
    while ($row = mysqli_fetch_assoc($res))
        $methods[] = $row;

    $billRes = mysqli_query($conn, "
        SELECT
            py.payment_id, py.payment_date, py.amount_paid, py.payment_method,
            py.payment_status,
            CONCAT(COALESCE(u.unit_name, u.unit_number,'—'), ' · ',
                   DATEDIFF(b.checkout_date, b.checkin_date), ' nights') AS description,
            p.property_name
        FROM payments py
        JOIN bookings    b  ON b.booking_id  = py.booking_id
        JOIN units       u  ON u.unit_id     = b.unit_id
        LEFT JOIN properties p ON p.property_id = u.property_id
        WHERE b.user_id=$userId
        ORDER BY py.payment_date DESC
        LIMIT 20
    ");
    $billing = [];
    while ($row = mysqli_fetch_assoc($billRes))
        $billing[] = $row;

    echo json_encode(['success' => true, 'methods' => $methods, 'billing' => $billing]);
    exit;
}

// ── POST ──────────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    require_verified_user_action(true);
    require_csrf_token(true);
    $action = $_POST['action'] ?? '';

    // ── add_card ──────────────────────────────────────────────────────────────
    if ($action === 'add_card') {
        $rawNumber = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
        $last4 = substr($rawNumber, -4);
        $provider = trim($_POST['provider'] ?? 'Visa');
        if (!in_array($provider, ['Visa', 'Mastercard', 'Amex', 'Card'], true)) {
            $provider = 'Card';
        }
        $holderName = trim($_POST['holder_name'] ?? '');
        $expMonth = (int) ($_POST['expiry_month'] ?? 0);
        $expYearRaw = (int) ($_POST['expiry_year'] ?? 0);

        // Normalise: accept both 2-digit (24) and 4-digit (2024) year
        $expYear = $expYearRaw < 100 ? 2000 + $expYearRaw : $expYearRaw;

        if (strlen($rawNumber) !== 16 || !$last4 || !$expMonth || !$expYear) {
            echo json_encode(['success' => false, 'message' => 'Card details incomplete.']);
            exit;
        }

        // Prevent duplicates for the same user
        $dupStmt = $conn->prepare(
            "SELECT id FROM payment_methods
             WHERE user_id = ? AND type = 'card' AND last4 = ?
               AND expiry_month = ? AND expiry_year = ? AND is_active = 1
             LIMIT 1"
        );
        $dupStmt->bind_param('isii', $userId, $last4, $expMonth, $expYear);
        $dupStmt->execute();
        $dupExists = $dupStmt->get_result()->num_rows > 0;
        $dupStmt->close();
        if ($dupExists) {
            echo json_encode(['success' => false, 'message' => 'This card is already saved.']);
            exit;
        }

        // First card for this user → make it default automatically
        $countStmt = $conn->prepare(
            "SELECT COUNT(*) AS cnt FROM payment_methods WHERE user_id = ? AND type = 'card' AND is_active = 1"
        );
        $countStmt->bind_param('i', $userId);
        $countStmt->execute();
        $countRow = $countStmt->get_result()->fetch_assoc();
        $countStmt->close();
        $setDefault = ($countRow['cnt'] == 0) ? 1 : 0;

        $label = $provider . ' ···· ' . $last4;

        $insStmt = $conn->prepare(
            "INSERT INTO payment_methods
             (user_id, type, provider, label, last4, expiry_month, expiry_year, holder_name, is_default)
             VALUES (?, 'card', ?, ?, ?, ?, ?, ?, ?)"
        );
        $insStmt->bind_param('isssiisi', $userId, $provider, $label, $last4, $expMonth, $expYear, $holderName, $setDefault);
        if ($insStmt->execute()) {
            $newId = $insStmt->insert_id;
            echo json_encode([
                'success' => true,
                'message' => 'Card added.',
                'id' => $newId,
                'last4' => $last4,
                'provider' => $provider,
                'expiry' => str_pad($expMonth, 2, '0', STR_PAD_LEFT) . '/' . $expYear,
                'is_default' => $setDefault,
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
        }
        $insStmt->close();
        exit;
    }

    // ── add_ewallet ───────────────────────────────────────────────────────────
    if ($action === 'add_ewallet') {
        $provider = trim($_POST['provider'] ?? '');
        $account = trim($_POST['account_number'] ?? '');
        $label = trim($_POST['label'] ?? $provider);

        if (!$provider || !$account) {
            echo json_encode(['success' => false, 'message' => 'Provider and account number required.']);
            exit;
        }

        // Prevent duplicate active wallet for same provider
        $dupStmt = $conn->prepare(
            "SELECT id FROM payment_methods
             WHERE user_id = ? AND type = 'ewallet' AND provider = ? AND is_active = 1 LIMIT 1"
        );
        $dupStmt->bind_param('is', $userId, $provider);
        $dupStmt->execute();
        $dupExists = $dupStmt->get_result()->num_rows > 0;
        $dupStmt->close();
        if ($dupExists) {
            // Update the account number instead
            $updStmt = $conn->prepare(
                "UPDATE payment_methods SET account_number = ?, label = ?
                 WHERE user_id = ? AND type = 'ewallet' AND provider = ? AND is_active = 1"
            );
            $updStmt->bind_param('ssis', $account, $label, $userId, $provider);
            $updStmt->execute();
            $updStmt->close();
            echo json_encode(['success' => true, 'message' => $provider . ' account updated.']);
            exit;
        }

        $insStmt = $conn->prepare(
            "INSERT INTO payment_methods (user_id, type, provider, account_number, label)
             VALUES (?, 'ewallet', ?, ?, ?)"
        );
        $insStmt->bind_param('isss', $userId, $provider, $account, $label);
        if ($insStmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => $provider . ' linked.',
                'id' => $insStmt->insert_id,
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
        }
        $insStmt->close();
        exit;
    }

    // ── remove ────────────────────────────────────────────────────────────────
    if ($action === 'remove') {
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
            exit;
        }

        // Don't allow removing the default card if others still exist
        $rowStmt = $conn->prepare('SELECT is_default FROM payment_methods WHERE id = ? AND user_id = ? LIMIT 1');
        $rowStmt->bind_param('ii', $id, $userId);
        $rowStmt->execute();
        $row = $rowStmt->get_result()->fetch_assoc();
        $rowStmt->close();
        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Not found.']);
            exit;
        }
        if ($row['is_default']) {
            $otherStmt = $conn->prepare(
                "SELECT COUNT(*) AS cnt FROM payment_methods WHERE user_id = ? AND type = 'card' AND is_active = 1 AND id != ?"
            );
            $otherStmt->bind_param('ii', $userId, $id);
            $otherStmt->execute();
            $others = $otherStmt->get_result()->fetch_assoc();
            $otherStmt->close();
            if ($others['cnt'] > 0) {
                echo json_encode(['success' => false, 'message' => 'Set another card as default before removing this one.']);
                exit;
            }
        }

        $rmStmt = $conn->prepare('UPDATE payment_methods SET is_active = 0 WHERE id = ? AND user_id = ?');
        $rmStmt->bind_param('ii', $id, $userId);
        if ($rmStmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Payment method removed.']);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
        }
        $rmStmt->close();
        exit;
    }

    // ── set_default ───────────────────────────────────────────────────────────
    if ($action === 'set_default') {
        $id = (int) ($_POST['id'] ?? 0);
        $type = ($_POST['type'] ?? 'card') === 'ewallet' ? 'ewallet' : 'card';
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
            exit;
        }

        // Verify the row belongs to this user
        $checkStmt = $conn->prepare(
            'SELECT id FROM payment_methods WHERE id = ? AND user_id = ? AND is_active = 1 LIMIT 1'
        );
        $checkStmt->bind_param('ii', $id, $userId);
        $checkStmt->execute();
        $check = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();
        if (!$check) {
            echo json_encode(['success' => false, 'message' => 'Not found.']);
            exit;
        }

        $clearStmt = $conn->prepare('UPDATE payment_methods SET is_default = 0 WHERE user_id = ? AND type = ?');
        $clearStmt->bind_param('is', $userId, $type);
        $clearStmt->execute();
        $clearStmt->close();
        $setStmt = $conn->prepare('UPDATE payment_methods SET is_default = 1 WHERE id = ? AND user_id = ?');
        $setStmt->bind_param('ii', $id, $userId);
        if ($setStmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Default payment method updated.']);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
        }
        $setStmt->close();
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}