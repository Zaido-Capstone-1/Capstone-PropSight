<?php
/**
 * API: /endpoints/user/saved.php
 * GET  — list saved units for current user
 * POST — toggle save/unsave
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';

if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? '') !== 'user')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $sort = $_GET['sort'] ?? 'date_desc';

    $orderBy = match($sort) {
        'price_asc'  => 'u.rent_amount ASC',
        'price_desc' => 'u.rent_amount DESC',
        'date_asc'   => 's.created_at ASC',
        default      => 's.created_at DESC',
    };

    $sql = "
        SELECT
            s.id AS saved_id, s.created_at AS saved_at,
            u.unit_id, u.unit_number, u.unit_name, u.unit_type,
            u.floor, u.rent_amount, u.status, u.description, u.max_guests,
            p.property_name, p.city, p.address,
            (SELECT ui.image_path FROM unit_images ui
             WHERE ui.unit_id=u.unit_id
             ORDER BY ui.sort_order ASC, ui.image_id ASC LIMIT 1) AS image_path
        FROM saved_units s
        JOIN units       u ON u.unit_id      = s.unit_id
        LEFT JOIN properties p ON p.property_id = u.property_id
        WHERE s.user_id=?
        ORDER BY $orderBy
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();

    $saved = [];
    while ($row = mysqli_fetch_assoc($res)) $saved[] = $row;

    echo json_encode(['success' => true, 'saved' => $saved, 'count' => count($saved)]);
    exit;
}

if ($method === 'POST') {
    require_csrf_token(true);
    $action = $_POST['action'] ?? 'toggle';
    $unitId = (int)($_POST['unit_id'] ?? 0);

    if (!$unitId) { echo json_encode(['success'=>false,'message'=>'unit_id required.']); exit; }

    if ($action === 'toggle') {
        $sel = $conn->prepare('SELECT id FROM saved_units WHERE user_id = ? AND unit_id = ? LIMIT 1');
        $sel->bind_param('ii', $userId, $unitId);
        $sel->execute();
        $existing = $sel->get_result()->fetch_assoc();
        $sel->close();

        if ($existing) {
            $del = $conn->prepare('DELETE FROM saved_units WHERE user_id = ? AND unit_id = ?');
            $del->bind_param('ii', $userId, $unitId);
            $del->execute();
            $del->close();
            echo json_encode(['success'=>true,'saved'=>false,'message'=>'Removed from saved.']);
        } else {
            $ins = $conn->prepare('INSERT INTO saved_units (user_id, unit_id) VALUES (?, ?)');
            $ins->bind_param('ii', $userId, $unitId);
            if ($ins->execute()) {
                echo json_encode(['success'=>true,'saved'=>true,'message'=>'Saved to wishlist.']);
            } else {
                echo json_encode(['success'=>false,'message'=>mysqli_error($conn)]);
            }
            $ins->close();
        }
        exit;
    }

    if ($action === 'remove') {
        $del = $conn->prepare('DELETE FROM saved_units WHERE user_id = ? AND unit_id = ?');
        $del->bind_param('ii', $userId, $unitId);
        if ($del->execute()) {
            echo json_encode(['success'=>true,'message'=>'Removed from saved.']);
        } else {
            echo json_encode(['success'=>false,'message'=>mysqli_error($conn)]);
        }
        $del->close();
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}
