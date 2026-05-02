<?php

/**
 * API: /api/expenses.php
 * GET  — list expenses with stats, trends, categories
 * POST — create / update / delete expense
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

$allowed_roles = ['admin', 'accounting', 'manager'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

function db_query($conn, string $sql, array $params = []): array
{
    $st = $conn->prepare($sql);
    if (!$st)
        return [];

    if (!empty($params)) {
        $types = '';
        $values = [];
        foreach ($params as $p) {
            if (is_int($p))
                $types .= 'i';
            elseif (is_float($p))
                $types .= 'd';
            else
                $types .= 's';
            $values[] = $p;
        }
        $st->bind_param($types, ...$values);
    }

    $st->execute();
    $result = $st->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $st->close();
    return $rows;
}

function db_execute($conn, string $sql, array $params = []): bool
{
    $st = $conn->prepare($sql);
    if (!$st)
        return false;

    if (!empty($params)) {
        $types = '';
        $values = [];
        foreach ($params as $p) {
            if (is_int($p))
                $types .= 'i';
            elseif (is_float($p))
                $types .= 'd';
            else
                $types .= 's';
            $values[] = $p;
        }
        $st->bind_param($types, ...$values);
    }

    $ok = $st->execute();
    $st->close();
    return $ok;
}

if ($method === 'GET') {
    $month = $_GET['month'] ?? date('Y-m');
    $search = trim($_GET['q'] ?? '');
    $category = trim($_GET['category'] ?? '');

    $parts = explode('-', $month);
    $year = (int) ($parts[0] ?? date('Y'));
    $mon = (int) ($parts[1] ?? date('n'));

    $date_from = sprintf('%04d-%02d-01', $year, $mon);
    $date_to = date('Y-m-t', strtotime($date_from));

    $stats_sql = "
        SELECT
            COALESCE(SUM(e.amount), 0) AS total,
            COALESCE(SUM(CASE WHEN e.expense_category = 'Maintenance' THEN e.amount END), 0) AS maintenance,
            COALESCE(SUM(CASE WHEN e.expense_category = 'Utilities'   THEN e.amount END), 0) AS utilities,
            COALESCE(SUM(CASE WHEN e.expense_category = 'Salaries'    THEN e.amount END), 0) AS salaries,
            COALESCE(SUM(CASE WHEN e.expense_category = 'Admin'       THEN e.amount END), 0) AS admin,
            COALESCE(SUM(CASE WHEN e.expense_category = 'Insurance'   THEN e.amount END), 0) AS insurance,
            COUNT(*) AS count
        FROM expenses e
        WHERE e.expense_date BETWEEN ? AND ?
    ";
    $stats_row = db_query($conn, $stats_sql, [$date_from, $date_to])[0] ?? [];
    $stats = [
        'total' => (float) ($stats_row['total'] ?? 0),
        'maintenance' => (float) ($stats_row['maintenance'] ?? 0),
        'utilities' => (float) ($stats_row['utilities'] ?? 0),
        'salaries' => (float) ($stats_row['salaries'] ?? 0),
        'admin' => (float) ($stats_row['admin'] ?? 0),
        'insurance' => (float) ($stats_row['insurance'] ?? 0),
        'count' => (int) ($stats_row['count'] ?? 0),
    ];

    // ── 6-month trend ─────────────────────────────
    $trends = [];
    for ($i = 5; $i >= 0; $i--) {
        $ts = strtotime("-$i months", strtotime($date_from));
        $mf = date('Y-m-01', $ts);
        $mt = date('Y-m-t', $ts);
        $row = db_query($conn, "SELECT COALESCE(SUM(amount),0) AS t FROM expenses WHERE expense_date BETWEEN ? AND ?", [$mf, $mt])[0] ?? ['t' => 0];
        $trends[] = [
            'label' => date('M', $ts),
            'amount' => (float) $row['t'],
        ];
    }

    $cat_rows = db_query(
        $conn,
        "SELECT expense_category, COALESCE(SUM(amount),0) AS total
         FROM expenses
         WHERE expense_date BETWEEN ? AND ?
         GROUP BY expense_category
         ORDER BY total DESC",
        [$date_from, $date_to]
    );
    $categories = array_map(fn($r) => [
        'category' => $r['expense_category'],
        'total' => (float) $r['total'],
    ], $cat_rows);

    // ── Expense records (with filters) ───────────
    $sql = "
        SELECT
            e.expense_id,
            e.expense_category,
            e.description,
            e.amount,
            e.expense_date,
            e.property_id,
            e.unit_id,
            COALESCE(NULLIF(p.property_name, ''), '—') AS property_name,
            COALESCE(NULLIF(u.unit_name, ''), NULLIF(u.unit_number, ''), '—') AS unit_name
        FROM expenses e
        LEFT JOIN properties p ON p.property_id = e.property_id
        LEFT JOIN units      u ON u.unit_id      = e.unit_id
        WHERE e.expense_date BETWEEN ? AND ?
    ";
    $params = [$date_from, $date_to];

    if ($search !== '') {
        $sql .= " AND (e.description LIKE ? OR p.property_name LIKE ? OR e.expense_category LIKE ?)";
        $like = "%$search%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if ($category !== '') {
        $sql .= " AND e.expense_category = ?";
        $params[] = $category;
    }

    $sql .= " ORDER BY e.expense_date DESC, e.expense_id DESC";

    $expenses = db_query($conn, $sql, $params);

    echo json_encode([
        'success' => true,
        'expenses' => $expenses,
        'stats' => $stats,
        'trends' => $trends,
        'categories' => $categories,
        'count' => count($expenses),
    ]);
    exit;
}

if ($method === 'POST') {
    require_csrf_token();
    $action = trim($_POST['action'] ?? '');

    //CREATE
    if ($action === 'create') {
        $property_id = (int) ($_POST['property_id'] ?? 0) ?: null;
        $unit_id = (int) ($_POST['unit_id'] ?? 0) ?: null;
        $category = trim($_POST['expense_category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $amount = (float) ($_POST['amount'] ?? 0);
        $date = trim($_POST['expense_date'] ?? date('Y-m-d'));
        $recorded_by = (int) $_SESSION['user_id'];

        if (!$category || !$description || $amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Category, description, and a positive amount are required.']);
            exit;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            echo json_encode(['success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD.']);
            exit;
        }

        $ok = db_execute(
            $conn,
            "INSERT INTO expenses (property_id, unit_id, expense_category, description, amount, expense_date, recorded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$property_id, $unit_id, $category, $description, $amount, $date, $recorded_by]
        );

        if (!$ok) {
            echo json_encode(['success' => false, 'message' => $conn->error]);
            exit;
        }

        $expense_id = $conn->insert_id;

        $ref = 'EXP-' . $expense_id . '-' . time();
        db_execute(
            $conn,
            "INSERT INTO transactions (reference_no, description, category, type, amount, transaction_date, property_id, notes, created_at)
             VALUES (?, ?, ?, 'Expense', ?, ?, ?, 'Logged via Expense Module', NOW())",
            [$ref, $description, $category, $amount, $date, $property_id]
        );

        echo json_encode(['success' => true, 'message' => 'Expense logged.', 'expense_id' => $expense_id]);
        exit;
    }

    //UPDATE
    if ($action === 'update') {
        $expense_id = (int) ($_POST['expense_id'] ?? 0);
        $property_id = (int) ($_POST['property_id'] ?? 0) ?: null;
        $unit_id = (int) ($_POST['unit_id'] ?? 0) ?: null;
        $category = trim($_POST['expense_category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $amount = (float) ($_POST['amount'] ?? 0);
        $date = trim($_POST['expense_date'] ?? '');

        if (!$expense_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid expense ID.']);
            exit;
        }
        if (!$category || !$description || $amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Category, description, and a positive amount are required.']);
            exit;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            echo json_encode(['success' => false, 'message' => 'Invalid date format.']);
            exit;
        }

        $ok = db_execute(
            $conn,
            "UPDATE expenses
             SET property_id = ?, unit_id = ?, expense_category = ?, description = ?, amount = ?, expense_date = ?
             WHERE expense_id = ?",
            [$property_id, $unit_id, $category, $description, $amount, $date, $expense_id]
        );

        if ($ok) {
            // Sync the linked transaction row
            db_execute(
                $conn,
                "UPDATE transactions SET description = ?, category = ?, amount = ?, transaction_date = ?, property_id = ? WHERE reference_no LIKE ?",
                [$description, $category, $amount, $date, $property_id, 'EXP-' . $expense_id . '-%']
            );
            echo json_encode(['success' => true, 'message' => 'Expense updated.']);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        exit;
    }

    //DELETE
    if ($action === 'delete') {
        $expense_id = (int) ($_POST['expense_id'] ?? 0);

        if (!$expense_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid expense ID.']);
            exit;
        }

        // Delete linked transaction first
        db_execute($conn, "DELETE FROM transactions WHERE reference_no LIKE ?", ["EXP-" . $expense_id . "-%"]);

        $ok = db_execute($conn, "DELETE FROM expenses WHERE expense_id = ?", [$expense_id]);

        if ($ok) {
            echo json_encode(['success' => true, 'message' => 'Expense deleted.']);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Method not allowed.']);