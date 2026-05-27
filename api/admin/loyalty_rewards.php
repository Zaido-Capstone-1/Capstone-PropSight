<?php
header('Content-Type: application/json');
ob_start();
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_not_blacklisted();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: list all rewards ────────────────────────────────────────────────────
if ($method === 'GET') {
    $res = $conn->query("SELECT * FROM loyalty_rewards ORDER BY points_cost ASC");
    $rewards = [];
    while ($row = $res->fetch_assoc())
        $rewards[] = $row;
    ob_clean();
    echo json_encode(['success' => true, 'rewards' => $rewards]);
    exit;
}

// ── POST: create / update / toggle / delete ──────────────────────────────────
if ($method === 'POST') {
    require_csrf_token(true);
    $action = trim($_POST['action'] ?? '');

    // ── CREATE ───────────────────────────────────────────────────────────────
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $pts = (int) ($_POST['points_cost'] ?? 0);
        $isActive = (int) ($_POST['is_active'] ?? 1);

        if (!$name) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Name is required.']);
            exit;
        }
        if ($pts < 1) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Points must be at least 1.']);
            exit;
        }

        $stmt = $conn->prepare(
            "INSERT INTO loyalty_rewards (name, description, points_cost, is_active) VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param('ssii', $name, $desc, $pts, $isActive);
        if ($stmt->execute()) {
            ob_clean();
            echo json_encode(['success' => true, 'message' => "\"$name\" added successfully.", 'reward_id' => $stmt->insert_id]);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'message' => $stmt->error]);
        }
        $stmt->close();
        exit;
    }

    // ── UPDATE ───────────────────────────────────────────────────────────────
    if ($action === 'update') {
        $id = (int) ($_POST['reward_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $pts = (int) ($_POST['points_cost'] ?? 0);
        $isActive = (int) ($_POST['is_active'] ?? 1);

        if (!$id) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid reward ID.']);
            exit;
        }
        if (!$name) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Name is required.']);
            exit;
        }
        if ($pts < 1) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Points must be at least 1.']);
            exit;
        }

        $stmt = $conn->prepare(
            "UPDATE loyalty_rewards SET name=?, description=?, points_cost=?, is_active=? WHERE reward_id=?"
        );
        $stmt->bind_param('ssiii', $name, $desc, $pts, $isActive, $id);
        if ($stmt->execute()) {
            ob_clean();
            echo json_encode(['success' => true, 'message' => "\"$name\" updated successfully."]);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'message' => $stmt->error]);
        }
        $stmt->close();
        exit;
    }

    // ── TOGGLE ───────────────────────────────────────────────────────────────
    if ($action === 'toggle') {
        $id = (int) ($_POST['reward_id'] ?? 0);
        $isActive = (int) ($_POST['is_active'] ?? 0);

        if (!$id) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid reward ID.']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE loyalty_rewards SET is_active=? WHERE reward_id=?");
        $stmt->bind_param('ii', $isActive, $id);
        if ($stmt->execute()) {
            $label = $isActive ? 'activated' : 'deactivated';
            ob_clean();
            echo json_encode(['success' => true, 'message' => "Reward $label."]);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'message' => $stmt->error]);
        }
        $stmt->close();
        exit;
    }

    // ── DELETE ───────────────────────────────────────────────────────────────
    if ($action === 'delete') {
        $id = (int) ($_POST['reward_id'] ?? 0);
        if (!$id) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid reward ID.']);
            exit;
        }

        // Fetch name before deleting for the response message
        $nameStmt = $conn->prepare("SELECT name FROM loyalty_rewards WHERE reward_id=? LIMIT 1");
        $nameStmt->bind_param('i', $id);
        $nameStmt->execute();
        $nameRow = $nameStmt->get_result()->fetch_assoc();
        $nameStmt->close();

        if (!$nameRow) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Reward not found.']);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM loyalty_rewards WHERE reward_id=?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            ob_clean();
            echo json_encode(['success' => true, 'message' => "\"{$nameRow['name']}\" deleted."]);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'message' => $stmt->error]);
        }
        $stmt->close();
        exit;
    }

    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

ob_clean();
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);