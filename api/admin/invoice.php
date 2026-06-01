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
define('SMTP_HOST', MAIL_HOST);
define('SMTP_PORT', (int) MAIL_PORT);
define('SMTP_SECURE', PHPMailer::ENCRYPTION_STARTTLS);
define('SMTP_USER', MAIL_USERNAME);
define('SMTP_PASS', MAIL_PASSWORD);
define('MAIL_FROM', MAIL_FROM_EMAIL);
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
  'check_paid' => handle_check_paid($conn),
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

  $issued_year = date('Y', strtotime($issued_date));
  $issued_month = date('m', strtotime($issued_date));

  // Count ALL invoices ever for this month to avoid reuse after deletions
  $cnt = $conn->prepare("SELECT COUNT(*) FROM invoices WHERE YEAR(issued_date) = ? AND MONTH(issued_date) = ?");
  $cnt->bind_param('ii', $issued_year, $issued_month);
  $cnt->execute();
  $cnt->bind_result($count);
  $cnt->fetch();
  $cnt->close();

  // Keep incrementing until we find a unique invoice_no
  do {
    $count++;
    $invoice_no = 'INV-' . $issued_year . $issued_month . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    $chk = $conn->prepare("SELECT id FROM invoices WHERE invoice_no = ?");
    $chk->bind_param('s', $invoice_no);
    $chk->execute();
    $chk->store_result();
    $exists = $chk->num_rows > 0;
    $chk->close();
  } while ($exists);

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
            i.issued_date,
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
  $total_raw = (float) $invoice['total'];
  $total = number_format($total_raw, 2);
  $due = htmlspecialchars($invoice['due_date']);
  $status = htmlspecialchars($invoice['status']);

  // ── Generate PayMongo payment link ────────────────────────────────────
  $payment_url = null;
  try {
    require_once __DIR__ . '/../../includes/paymongo.php';
    $amount_int = (int) round($total_raw);   // centavo conversion done inside paymongo_create_link
    if ($amount_int >= 1) {
      // Re-use an existing active link for this invoice if available
      $exStmt = $conn->prepare("
                SELECT checkout_url FROM paymongo_payments
                WHERE reference_id = ? AND reference_type = 'invoice'
                  AND status NOT IN ('paid','expired','failed')
                ORDER BY created_at DESC LIMIT 1
            ");
      if ($exStmt) {
        $exStmt->bind_param('i', $id);
        $exStmt->execute();
        $exRow = $exStmt->get_result()->fetch_assoc();
        $exStmt->close();
        if ($exRow && !empty($exRow['checkout_url'])) {
          $payment_url = $exRow['checkout_url'];
        }
      }

      if (!$payment_url) {
        $link = paymongo_create_link(
          $amount_int,
          "PropSight Invoice {$invoice_no} — {$unit}",
          ['invoice_id' => $id, 'invoice_no' => $invoice_no]
        );
        if (!empty($link['attributes']['checkout_url'])) {
          $payment_url = $link['attributes']['checkout_url'];
          $link_id = $link['id'] ?? null;
          $link_status = 'pending';

          // Try to persist; silently ignore if paymongo_payments table schema
          // doesn't have reference_id/reference_type columns yet — add them when ready.
          $insStmt = $conn->prepare("
                        INSERT IGNORE INTO paymongo_payments
                            (booking_id, user_id, paymongo_link_id, checkout_url, amount, status,
                             payment_method, reference_id, reference_type, created_at, expires_at)
                        VALUES (0, 0, ?, ?, ?, ?, '', ?, 'invoice', NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR))
                    ");
          if ($insStmt) {
            $insStmt->bind_param('ssdsi', $link_id, $payment_url, $total_raw, $link_status, $id);
            $insStmt->execute();
            $insStmt->close();
          }
        }
      }
    }
  } catch (Throwable $pm_err) {
    // PayMongo failure is non-fatal — send email without payment link
    error_log('[invoice send] PayMongo error: ' . $pm_err->getMessage());
  }

  // ── Send email ────────────────────────────────────────────────────────
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
    $mail->Subject = "Invoice {$invoice_no} — Payment Due";
    $mail->Body = build_email_body($invoice_no, $name, $unit, $items, $total, $due, $status, $payment_url);
    $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $mail->Body));

    $mail->send();

  } catch (Exception $e) {
    json_response(false, 'Email failed: ' . $e->getMessage());
  }

  $upd = $conn->prepare("UPDATE invoices SET status = 'Sent' WHERE id = ?");
  $upd->bind_param('i', $id);
  $upd->execute();

  json_response(true, 'Invoice sent successfully.', [
    'invoice_id' => $id,
    'checkout_url' => $payment_url,
  ]);
}

/* ─── EMAIL BODY BUILDER ──────────────────────────────────────────────── */

function build_email_body(
  string $invoice_no,
  string $name,
  string $unit,
  string $items,
  string $total,
  string $due,
  string $status,
  ?string $payment_url = null
): string {

  $paymongo_block = '';
  if ($payment_url) {
    $safe_url = htmlspecialchars($payment_url, ENT_QUOTES);
    $paymongo_block = <<<PMBLOCK

          <!-- PayMongo CTA -->
          <div style="text-align:center;margin-bottom:24px;">
            <p style="margin:0 0 10px;font-size:13px;color:#64748b;">
              Pay securely online via GCash, Maya, card, and more:
            </p>
            <a href="{$safe_url}" target="_blank"
               style="display:inline-block;padding:13px 34px;background:#1e40af;color:#fff;
                      font-size:14px;font-weight:700;border-radius:8px;text-decoration:none;
                      letter-spacing:.3px;">
              💳&nbsp; Pay &#8369;{$total} Now
            </a>
            <p style="margin:8px 0 0;font-size:11px;color:#94a3b8;">
              Powered by PayMongo &middot; Secure &amp; encrypted
            </p>
          </div>
        PMBLOCK;
  }

  $year = date('Y');

  return <<<HTML
    <div style="font-family:'Segoe UI',Arial,sans-serif;max-width:560px;margin:0 auto;background:#f8fafc;padding:32px 16px;">

      <div style="background:#fff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;">

        <!-- Header -->
        <div style="background:#1e40af;padding:28px 32px;">
          <div style="font-size:10px;font-weight:700;letter-spacing:3px;color:rgba(255,255,255,.65);text-transform:uppercase;margin-bottom:6px;">Invoice</div>
          <div style="font-size:24px;font-weight:800;color:#fff;">{$invoice_no}</div>
          <div style="margin-top:10px;display:inline-block;padding:3px 12px;border-radius:20px;
                      background:rgba(255,255,255,.15);font-size:11px;font-weight:700;
                      color:rgba(255,255,255,.9);letter-spacing:.8px;text-transform:uppercase;">
            {$status}
          </div>
        </div>

        <div style="padding:28px 32px;">

          <!-- Greeting -->
          <p style="margin:0 0 6px;font-size:15px;color:#1e2533;font-weight:600;">Hello, {$name}</p>
          <p style="margin:0 0 24px;font-size:13.5px;color:#64748b;line-height:1.65;">
            An invoice has been issued for your accommodation. Please review the details below and settle your balance on or before the due date.
          </p>

          <!-- Invoice details -->
          <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;margin-bottom:24px;">
            <table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:13.5px;">
              <tr>
                <td style="padding:11px 16px;background:#f1f5f9;font-weight:700;color:#475569;width:36%;border-bottom:1px solid #e2e8f0;">Unit</td>
                <td style="padding:11px 16px;color:#1e2533;border-bottom:1px solid #e2e8f0;">{$unit}</td>
              </tr>
              <tr>
                <td style="padding:11px 16px;background:#f1f5f9;font-weight:700;color:#475569;border-bottom:1px solid #e2e8f0;">Items</td>
                <td style="padding:11px 16px;color:#1e2533;border-bottom:1px solid #e2e8f0;">{$items}</td>
              </tr>
              <tr>
                <td style="padding:12px 16px;background:#f1f5f9;font-weight:700;color:#475569;border-bottom:1px solid #e2e8f0;">Total Amount</td>
                <td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;">
                  <span style="font-size:19px;font-weight:800;color:#16a34a;">&#8369;{$total}</span>
                </td>
              </tr>
              <tr>
                <td style="padding:11px 16px;background:#f1f5f9;font-weight:700;color:#475569;">Due Date</td>
                <td style="padding:11px 16px;color:#dc2626;font-weight:600;">{$due}</td>
              </tr>
            </table>
          </div>

          {$paymongo_block}

          <!-- Important note -->
          <div style="background:#fffbeb;border-radius:9px;padding:13px 16px;border-left:3px solid #f59e0b;margin-bottom:24px;">
            <div style="font-size:11px;font-weight:800;color:#92400e;margin-bottom:4px;letter-spacing:.5px;">IMPORTANT</div>
            <div style="font-size:12.5px;color:#92400e;line-height:1.65;">
              Use <strong>{$invoice_no}</strong> as your payment reference. After paying, please send your proof of payment via the <strong>Messages page</strong> in your account.
            </div>
          </div>

          <!-- Footer -->
          <p style="margin:0;font-size:11.5px;color:#94a3b8;border-top:1px solid #f1f5f9;padding-top:16px;line-height:1.6;">
            This is an automated message from Boracay Accommodation. Please do not reply to this email.<br>
            &copy; {$year} Boracay Accommodation
          </p>

        </div>
      </div>
    </div>
    HTML;
}

/* ─── CHECK PAID (admin poll — active PayMongo sync) ─────────────────── */

function handle_check_paid(mysqli $conn): void
{
  $id = (int) ($_POST['id'] ?? 0);
  if (!$id)
    json_response(false, 'Missing invoice ID.');

  // Fast path: already marked Paid in our DB
  $inv = $conn->prepare("SELECT status FROM invoices WHERE id = ? LIMIT 1");
  $inv->bind_param('i', $id);
  $inv->execute();
  $row = $inv->get_result()->fetch_assoc();
  $inv->close();

  if (!$row)
    json_response(false, 'Invoice not found.');
  if ($row['status'] === 'Paid') {
    json_response(true, 'Already paid.', ['is_paid' => true]);
  }

  // Look up the active PayMongo link for this invoice
  $pmStmt = $conn->prepare("
        SELECT paymongo_link_id, amount, status
        FROM paymongo_payments
        WHERE reference_id = ? AND reference_type = 'invoice'
        ORDER BY created_at DESC LIMIT 1
    ");
  $pmStmt->bind_param('i', $id);
  $pmStmt->execute();
  $pmRow = $pmStmt->get_result()->fetch_assoc();
  $pmStmt->close();

  if (!$pmRow || empty($pmRow['paymongo_link_id'])) {
    // No link yet — not paid
    json_response(true, 'No payment link.', ['is_paid' => false]);
  }

  if ($pmRow['status'] === 'paid') {
    // paymongo_payments already paid but invoices not updated yet — fix it
    $fix = $conn->prepare("UPDATE invoices SET status = 'Paid' WHERE id = ? AND status != 'Paid'");
    $fix->bind_param('i', $id);
    $fix->execute();
    $fix->close();
    json_response(true, 'Synced.', ['is_paid' => true]);
  }

  // Actively ask PayMongo for the live link status
  require_once __DIR__ . '/../../includes/paymongo.php';
  try {
    $link = paymongo_request('GET', '/links/' . $pmRow['paymongo_link_id']);
    $linkStatus = $link['attributes']['status'] ?? '';

    if ($linkStatus !== 'paid') {
      json_response(true, 'Not paid yet.', ['is_paid' => false]);
    }

    // PayMongo confirms paid — sync everything
    $paymentId = $link['attributes']['payments'][0]['id'] ?? null;
    $paidAt = date('Y-m-d H:i:s');
    $amount = (float) $pmRow['amount'];
    $date = date('Y-m-d');

    // 1. Mark paymongo_payments
    $updPm = $conn->prepare("UPDATE paymongo_payments SET status='paid', paymongo_payment_id=?, paid_at=? WHERE paymongo_link_id=?");
    $updPm->bind_param('sss', $paymentId, $paidAt, $pmRow['paymongo_link_id']);
    $updPm->execute();
    $updPm->close();

    // 2. Mark invoice Paid
    $updInv = $conn->prepare("UPDATE invoices SET status='Paid' WHERE id=? AND status!='Paid'");
    $updInv->bind_param('i', $id);
    $updInv->execute();
    $updInv->close();

    // 3. Log transaction (skip if already recorded)
    $invRef = 'INV-PMT-' . $id;
    $txCheck = $conn->query("SELECT id FROM transactions WHERE reference_no='" . $conn->real_escape_string($invRef) . "' LIMIT 1");
    if ($txCheck && $txCheck->num_rows === 0) {
      $desc = 'PayMongo payment for Invoice #' . $id;
      $cat = 'Invoice Revenue';
      $typ = 'Income';
      $txStmt = $conn->prepare("INSERT INTO transactions (reference_no, description, category, type, amount, transaction_date) VALUES (?, ?, ?, ?, ?, ?)");
      $txStmt->bind_param('ssssds', $invRef, $desc, $cat, $typ, $amount, $date);
      $txStmt->execute();
      $txStmt->close();
    }

    json_response(true, 'Payment confirmed.', ['is_paid' => true]);

  } catch (Throwable $e) {
    error_log('[check_paid] PayMongo poll failed: ' . $e->getMessage());
    json_response(true, 'Poll failed.', ['is_paid' => false]);
  }
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