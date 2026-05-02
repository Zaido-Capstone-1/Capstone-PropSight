<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

include '../../includes/session.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}
require_csrf_token();

include '../../includes/db.php';
require_once __DIR__ . '/../../config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/SMTP.php';

// SMTP config loaded from .env via config.php — no credentials in source code
define('SMTP_HOST',      MAIL_HOST);
define('SMTP_PORT',      (int) MAIL_PORT);
define('SMTP_SECURE',    PHPMailer::ENCRYPTION_STARTTLS);
define('SMTP_USER',      MAIL_USERNAME);
define('SMTP_PASS',      MAIL_PASSWORD);
define('MAIL_FROM',      MAIL_FROM_EMAIL);
define('INV_MAIL_FROM_NAME', MAIL_FROM_NAME);

define('CAFILE_PATH', realpath(__DIR__ . '/../../includes/ssl/cacert.pem'));


function json_response(bool $success, string $message, array $extra = []): void
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

function require_post(string ...$keys): void
{
    foreach ($keys as $key) {
        if (empty($_POST[$key])) {
            json_response(false, "Missing required field: {$key}");
        }
    }
}


$action = trim($_POST['action'] ?? '');

if (!$action) {
    json_response(false, 'No action provided.');
}

match ($action) {
    'create' => handle_create($conn),
    'update_status' => handle_update_status($conn),
    'delete' => handle_delete($conn),
    'get_invoice' => handle_get_invoice($conn),
    'get_stats' => handle_get_stats($conn),
    'send' => handle_send($conn),
    default => json_response(false, 'Invalid action.')
};


function handle_create(mysqli $conn): void
{
    require_post('tenant_id', 'issued_date', 'due_date', 'items');

    $tenant_id = (int) $_POST['tenant_id'];
    $unit = trim($_POST['unit'] ?? '');
    $issued_date = trim($_POST['issued_date']);
    $due_date = trim($_POST['due_date']);
    $items = trim($_POST['items']);
    $total = (float) ($_POST['total'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['Pending', 'Paid', 'Overdue'], true)
        ? $_POST['status'] : 'Pending';

    $year = date('Y');
    $month = date('m');

    $cnt = $conn->prepare("SELECT COUNT(*) FROM invoices WHERE YEAR(issued_date) = ? AND MONTH(issued_date) = ?");
    $cnt->bind_param('ii', $year, $month);
    $cnt->execute();
    $cnt->bind_result($count);
    $cnt->fetch();
    $cnt->close();

    $invoice_no = 'INV-' . $year . $month . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

    $stmt = $conn->prepare("
        INSERT INTO invoices (invoice_no, tenant_id, unit, issued_date, due_date, items, total, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('sissssds', $invoice_no, $tenant_id, $unit, $issued_date, $due_date, $items, $total, $status);

    if (!$stmt->execute()) {
        json_response(false, 'Failed to create invoice: ' . $stmt->error);
    }

    $new_id = $conn->insert_id;
    json_response(true, 'Invoice created successfully.', ['invoice_no' => $invoice_no, 'invoice_id' => $new_id]);
}


function handle_update_status(mysqli $conn): void
{
    $id = (int) ($_POST['id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    $allowed = ['Paid', 'Pending', 'Overdue', 'Sent'];

    if (!$id)
        json_response(false, 'Missing invoice ID.');
    if (!in_array($status, $allowed, true))
        json_response(false, 'Invalid status value.');

    $stmt = $conn->prepare('UPDATE invoices SET status = ? WHERE id = ?');
    $stmt->bind_param('si', $status, $id);

    if (!$stmt->execute()) {
        json_response(false, 'Failed to update status: ' . $stmt->error);
    }

    json_response(true, 'Status updated.');
}


function handle_delete(mysqli $conn): void
{
    $id = (int) ($_POST['id'] ?? 0);
    if (!$id)
        json_response(false, 'Missing invoice ID.');

    $stmt = $conn->prepare('DELETE FROM invoices WHERE id = ?');
    $stmt->bind_param('i', $id);

    if (!$stmt->execute()) {
        json_response(false, 'Failed to delete invoice: ' . $stmt->error);
    }

    json_response(true, 'Invoice deleted.');
}

function handle_get_invoice(mysqli $conn): void
{
    $id = (int) ($_POST['id'] ?? 0);
    if (!$id)
        json_response(false, 'Missing invoice ID.');

    $stmt = $conn->prepare("
        SELECT
            i.id,
            i.invoice_no,
            i.unit,
            i.items,
            i.total,
            i.status,
            DATE_FORMAT(i.issued_date, '%b %d, %Y') AS issued_label,
            DATE_FORMAT(i.due_date,    '%b %d, %Y') AS due_label,
            t.full_name,
            t.email
        FROM invoices i
        JOIN tenants t ON t.tenant_id = i.tenant_id
        WHERE i.id = ?
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $invoice = $stmt->get_result()->fetch_assoc();

    if (!$invoice)
        json_response(false, 'Invoice not found.');

    json_response(true, 'OK', ['invoice' => $invoice]);
}

function handle_send(mysqli $conn): void
{
    $id = (int) ($_POST['id'] ?? 0);
    if (!$id)
        json_response(false, 'Missing invoice ID.');

    $stmt = $conn->prepare("
        SELECT i.*, t.email, t.full_name
        FROM invoices i
        JOIN tenants t ON t.tenant_id = i.tenant_id
        WHERE i.id = ?
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $invoice = $stmt->get_result()->fetch_assoc();
    if (!$invoice)
        json_response(false, 'Invoice not found.');
    if (!$invoice['email'])
        json_response(false, 'Tenant has no email address.');

    $invoice_no = $invoice['invoice_no'] ?? "#{$id}";
    $name = htmlspecialchars($invoice['full_name']); 
    $unit = htmlspecialchars($invoice['unit']);
    $items = htmlspecialchars($invoice['items']);
    $total = number_format((float) $invoice['total'], 2);
    $due = htmlspecialchars($invoice['due_date']);
    $status = htmlspecialchars($invoice['status']);

    try {
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8'; 

        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;

        $cafile = realpath(__DIR__ . '/../../includes/ssl/cacert.pem');
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => $cafile ? true : false,
                'verify_peer_name' => $cafile ? true : false,
                'allow_self_signed' => $cafile ? false : true,
                'cafile' => $cafile ?: null,
            ],
        ];

        $mail->setFrom(MAIL_FROM, INV_MAIL_FROM_NAME);
        $mail->addAddress($invoice['email'], $invoice['full_name']);
        $mail->addReplyTo(MAIL_FROM, INV_MAIL_FROM_NAME);

        $mail->isHTML(true);
        $mail->Subject = "Invoice {$invoice_no} — Payment Due"; // raw UTF-8 string
        $mail->Body = build_email_body($invoice_no, $name, $unit, $items, $total, $due, $status);
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $mail->Body));

        $mail->send();

    } catch (Exception $e) {
        json_response(false, 'Email failed: ' . $e->getMessage());
    }

    $upd = $conn->prepare("UPDATE invoices SET status = 'Sent' WHERE id = ?");
    $upd->bind_param('i', $id);
    $upd->execute();

    json_response(true, 'Invoice sent successfully.');
}

/* ─── EMAIL BODY BUILDER ──────────────────────────────────────────────── */

function build_email_body(
    string $invoice_no,
    string $name,
    string $unit,
    string $items,
    string $total,
    string $due,
    string $status
): string {
    return <<<HTML
    <div style="font-family:'Segoe UI',Arial,sans-serif;max-width:560px;margin:0 auto;background:#f8fafc;padding:32px 16px;">
      <div style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08);">

        <div style="background:linear-gradient(135deg,#1e40af,#3b82f6);padding:28px 32px;">
          <div style="font-size:11px;font-weight:700;letter-spacing:2px;color:rgba(255,255,255,.7);text-transform:uppercase;margin-bottom:4px;">Invoice</div>
          <div style="font-size:22px;font-weight:700;color:#fff;">{$invoice_no}</div>
        </div>

        <div style="padding:28px 32px;">
          <p style="margin:0 0 20px;font-size:15px;color:#374151;">Hello <strong>{$name}</strong>,</p>
          <p style="margin:0 0 24px;font-size:14px;color:#6b7280;line-height:1.6;">
            Please find your invoice details below. Kindly settle your balance on or before the due date.
          </p>

          <table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:13.5px;margin-bottom:24px;">
            <tr>
              <td style="padding:11px 14px;background:#f1f5f9;font-weight:600;color:#374151;width:38%;border:1px solid #e2e8f0;">Unit</td>
              <td style="padding:11px 14px;border:1px solid #e2e8f0;color:#374151;">{$unit}</td>
            </tr>
            <tr>
              <td style="padding:11px 14px;background:#f1f5f9;font-weight:600;color:#374151;border:1px solid #e2e8f0;">Items</td>
              <td style="padding:11px 14px;border:1px solid #e2e8f0;color:#374151;">{$items}</td>
            </tr>
            <tr>
              <td style="padding:11px 14px;background:#f1f5f9;font-weight:600;color:#374151;border:1px solid #e2e8f0;">Total Amount</td>
              <td style="padding:11px 14px;border:1px solid #e2e8f0;font-weight:700;font-size:16px;color:#16a34a;">&#8369;{$total}</td>
            </tr>
            <tr>
              <td style="padding:11px 14px;background:#f1f5f9;font-weight:600;color:#374151;border:1px solid #e2e8f0;">Due Date</td>
              <td style="padding:11px 14px;border:1px solid #e2e8f0;color:#374151;">{$due}</td>
            </tr>
            <tr>
              <td style="padding:11px 14px;background:#f1f5f9;font-weight:600;color:#374151;border:1px solid #e2e8f0;">Status</td>
              <td style="padding:11px 14px;border:1px solid #e2e8f0;">
                <span style="display:inline-block;padding:3px 10px;border-radius:20px;background:#fef3c7;color:#92400e;font-size:12px;font-weight:600;">{$status}</span>
              </td>
            </tr>
          </table>

          <!-- How to Pay -->
          <div style="margin-bottom:24px;border-radius:10px;overflow:hidden;border:1px solid #e2e8f0;">
            <div style="background:#1e40af;padding:12px 18px;">
              <span style="font-size:13px;font-weight:700;color:#fff;letter-spacing:.4px;">HOW TO PAY</span>
            </div>
            <div style="padding:18px;background:#f8fafc;">

              <!-- 2x2 grid using table for email client compatibility -->
              <table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin-bottom:14px;">
                <tr>
                  <!-- GCash -->
                  <td style="width:50%;padding:0 8px 14px 0;vertical-align:top;">
                    <div style="display:flex;align-items:flex-start;gap:10px;">
                      <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/b/b7/GCash_logo.svg/120px-GCash_logo.svg.png" width="34" height="34" alt="GCash" style="border-radius:8px;object-fit:contain;background:#0070FF;padding:4px;box-sizing:border-box;">
                      <div>
                        <div style="font-size:13px;font-weight:700;color:#374151;margin-bottom:2px;">GCash</div>
                        <div style="font-size:12px;color:#6b7280;line-height:1.5;">+63 912 345 6789<br>Juan dela Cruz</div>
                      </div>
                    </div>
                  </td>
                  <!-- Maya -->
                  <td style="width:50%;padding:0 0 14px 8px;vertical-align:top;">
                    <div style="display:flex;align-items:flex-start;gap:10px;">
                      <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/a/a0/Maya_logo.svg/120px-Maya_logo.svg.png" width="34" height="34" alt="Maya" style="border-radius:8px;object-fit:contain;background:#00A651;padding:4px;box-sizing:border-box;">
                      <div>
                        <div style="font-size:13px;font-weight:700;color:#374151;margin-bottom:2px;">Maya</div>
                        <div style="font-size:12px;color:#6b7280;line-height:1.5;">+63 912 345 6789<br>Juan dela Cruz</div>
                      </div>
                    </div>
                  </td>
                </tr>
                <tr>
                  <!-- Bank Transfer -->
                  <td style="width:50%;padding:0 8px 0 0;vertical-align:top;">
                    <div style="display:flex;align-items:flex-start;gap:10px;">
                      <div style="min-width:34px;width:34px;height:34px;border-radius:8px;background:#1e40af;text-align:center;line-height:34px;font-size:18px;">🏦</div>
                      <div>
                        <div style="font-size:13px;font-weight:700;color:#374151;margin-bottom:2px;">Bank Transfer</div>
                        <div style="font-size:12px;color:#6b7280;line-height:1.5;">BDO — 1234 5678 9012<br>Juan dela Cruz</div>
                      </div>
                    </div>
                  </td>
                  <!-- PayPal -->
                  <td style="width:50%;padding:0 0 0 8px;vertical-align:top;">
                    <div style="display:flex;align-items:flex-start;gap:10px;">
                      <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/b/b5/PayPal.svg/120px-PayPal.svg.png" width="34" height="34" alt="PayPal" style="border-radius:8px;object-fit:contain;background:#fff;padding:4px;box-sizing:border-box;border:1px solid #e2e8f0;">
                      <div>
                        <div style="font-size:13px;font-weight:700;color:#374151;margin-bottom:2px;">PayPal</div>
                        <div style="font-size:12px;color:#6b7280;line-height:1.5;">paypal.me/juandelacruz</div>
                      </div>
                    </div>
                  </td>
                </tr>
              </table>

              <!-- Reference instruction -->
              <div style="background:#fef9c3;border-radius:8px;padding:12px 14px;border-left:3px solid #f59e0b;">
                <div style="font-size:12px;font-weight:700;color:#92400e;margin-bottom:4px;">IMPORTANT</div>
                <div style="font-size:12px;color:#92400e;line-height:1.6;">
                  Use <strong>{$invoice_no}</strong> as your payment reference. After paying, send your proof of payment (screenshot or receipt) via the <strong>Messages page</strong> in your PropSight account.
                </div>
              </div>

            </div>
          </div>

          <p style="margin:0;font-size:12px;color:#9ca3af;border-top:1px solid #f1f5f9;padding-top:16px;">
            This is an automated invoice from your property management system. Please do not reply to this email.
          </p>
        </div>
      </div>
    </div>
    HTML;
}

/* ─── GET STATS (live stat card refresh) ─────────────────────────────── */

function handle_get_stats(mysqli $conn): void
{
    $result = $conn->query("
        SELECT
            COUNT(*)                AS total,
            SUM(status = 'Paid')    AS paid,
            SUM(status = 'Pending') AS pending,
            SUM(status = 'Overdue') AS overdue
        FROM invoices
    ");

    $stats = $result->fetch_assoc();

    json_response(true, 'OK', [
        'stats' => [
            'total' => (int) $stats['total'],
            'paid' => (int) $stats['paid'],
            'pending' => (int) $stats['pending'],
            'overdue' => (int) $stats['overdue'],
        ]
    ]);
}