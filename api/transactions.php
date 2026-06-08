<?php
/**
 * API: /api/transactions.php
 * GET  — list all transactions for the year with stats
 * POST — add / delete transaction
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
    $year = (int) ($_GET['year'] ?? date('Y'));

    // Summary stats
    $income = (float) (mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT COALESCE(SUM(amount),0) AS v FROM transactions
         WHERE type='Income' AND YEAR(transaction_date)=$year"
    ))['v'] ?? 0);
    $expense = (float) (mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT COALESCE(SUM(amount),0) AS v FROM transactions
         WHERE type='Expense' AND YEAR(transaction_date)=$year"
    ))['v'] ?? 0);
    $count = (int) (mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT COUNT(*) AS c FROM transactions WHERE YEAR(transaction_date)=$year"
    ))['c'] ?? 0);

    // All rows
    $res = mysqli_query($conn, "
        SELECT
            t.id,
            DATE_FORMAT(t.transaction_date,'%b %d') AS date_label,
            DATE_FORMAT(t.transaction_date,'%Y-%m') AS month_val,
            t.transaction_date,
            MONTH(t.transaction_date) AS month_num,
            t.reference_no, t.description, t.category,
            t.type, t.amount,
            COALESCE(
                p.property_name,
                bp.property_name,
                '—'
            ) AS property_name
        FROM transactions t
        LEFT JOIN properties p  ON p.property_id  = t.property_id
        LEFT JOIN bookings   bk ON bk.booking_id  = t.booking_id
        LEFT JOIN units      bu ON bu.unit_id      = bk.unit_id
        LEFT JOIN properties bp ON bp.property_id  = bu.property_id
        WHERE YEAR(t.transaction_date) = $year
        ORDER BY t.transaction_date DESC, t.id DESC
    ");
    $rows = [];
    $categories = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
        if ($row['category'])
            $categories[$row['category']] = true;
    }

    // Monthly breakdown for charts
    $monthly = [];
    $mRes = mysqli_query($conn, "
        SELECT MONTH(transaction_date) AS m,
               COALESCE(SUM(CASE WHEN type='Income'  THEN amount END),0) AS income,
               COALESCE(SUM(CASE WHEN type='Expense' THEN amount END),0) AS expense
        FROM transactions
        WHERE YEAR(transaction_date)=$year
        GROUP BY m ORDER BY m
    ");
    while ($row = mysqli_fetch_assoc($mRes)) {

        fmt_dt_row($row);

        $monthly[] = $row;

    }

    echo json_encode([
        'success' => true,
        'rows' => $rows,
        'stats' => [
            'total_income' => $income,
            'total_expense' => $expense,
            'net_profit' => $income - $expense,
            'total_count' => $count,
        ],
        'categories' => array_keys($categories),
        'monthly' => $monthly,
    ]);
    exit;
}

if ($method === 'POST') {
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $ref = mysqli_real_escape_string($conn, trim($_POST['reference_no'] ?? 'TXN-' . time()));
        $desc = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));
        $cat = mysqli_real_escape_string($conn, trim($_POST['category'] ?? ''));
        $type = in_array($_POST['type'] ?? '', ['Income', 'Expense']) ? $_POST['type'] : 'Income';
        $amount = (float) ($_POST['amount'] ?? 0);
        $date = mysqli_real_escape_string($conn, trim($_POST['transaction_date'] ?? date('Y-m-d')));
        $propId = (int) ($_POST['property_id'] ?? 0) ?: 'NULL';
        $notes = mysqli_real_escape_string($conn, trim($_POST['notes'] ?? ''));
        $recBy = (int) $_SESSION['user_id'];

        if ($amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Amount must be > 0.']);
            exit;
        }

        $sql = "INSERT INTO transactions
            (reference_no, description, category, type, amount, transaction_date, property_id, notes, recorded_by)
            VALUES ('$ref','$desc','$cat','$type',$amount,'$date',$propId,'$notes',$recBy)";

        if (mysqli_query($conn, $sql)) {
            echo json_encode(['success' => true, 'message' => 'Transaction added.', 'id' => mysqli_insert_id($conn)]);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
        }
        exit;
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
            exit;
        }
        if (mysqli_query($conn, "DELETE FROM transactions WHERE id=$id")) {
            echo json_encode(['success' => true, 'message' => 'Transaction deleted.']);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}