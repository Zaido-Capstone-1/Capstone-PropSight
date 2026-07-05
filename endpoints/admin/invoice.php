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

// Payment methods offered in the email invoice
define('INVOICE_PAYMENT_METHODS', [
  'gcash' => ['label' => 'GCash', 'svg' => '<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"32\" height=\"32\" viewBox=\"0 0 32 32\"><circle cx=\"16\" cy=\"16\" r=\"16\" fill=\"#007aff\"/><text x=\"16\" y=\"22\" font-family=\"Arial,sans-serif\" font-size=\"18\" font-weight=\"800\" text-anchor=\"middle\" fill=\"#fff\">G</text></svg>', 'color' => '#0055cc', 'bg' => '#e8f0ff', 'border' => '#b3cbf7'],
  'paymaya' => ['label' => 'Maya', 'svg' => '<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"32\" height=\"32\" viewBox=\"0 0 32 32\"><rect width=\"32\" height=\"32\" rx=\"8\" fill=\"#00b14f\"/><text x=\"16\" y=\"22\" font-family=\"Arial,sans-serif\" font-size=\"18\" font-weight=\"800\" text-anchor=\"middle\" fill=\"#fff\">M</text></svg>', 'color' => '#006b35', 'bg' => '#e6f9ef', 'border' => '#a3dfc0'],
  'card' => ['label' => 'Credit / Debit Card', 'svg' => '<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"32\" height=\"32\" viewBox=\"0 0 32 32\"><rect width=\"32\" height=\"32\" rx=\"6\" fill=\"#1e40af\"/><rect x=\"4\" y=\"9\" width=\"24\" height=\"14\" rx=\"2\" fill=\"none\" stroke=\"#fff\" stroke-width=\"1.5\"/><rect x=\"4\" y=\"13\" width=\"24\" height=\"4\" fill=\"#fff\" opacity=\"0.9\"/><rect x=\"6\" y=\"19\" width=\"6\" height=\"2\" rx=\"1\" fill=\"#fff\"/><rect x=\"14\" y=\"19\" width=\"4\" height=\"2\" rx=\"1\" fill=\"#fff\" opacity=\"0.6\"/></svg>', 'color' => '#1e40af', 'bg' => '#eef2ff', 'border' => '#a5b4fc'],
  'dob' => ['label' => 'Online Banking', 'svg' => '<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"32\" height=\"32\" viewBox=\"0 0 32 32\"><rect width=\"32\" height=\"32\" rx=\"6\" fill=\"#374151\"/><polygon points=\"16,6 6,12 26,12\" fill=\"#fff\"/><rect x=\"8\" y=\"13\" width=\"3\" height=\"9\" rx=\"1\" fill=\"#fff\"/><rect x=\"14.5\" y=\"13\" width=\"3\" height=\"9\" rx=\"1\" fill=\"#fff\"/><rect x=\"21\" y=\"13\" width=\"3\" height=\"9\" rx=\"1\" fill=\"#fff\"/><rect x=\"6\" y=\"23\" width=\"20\" height=\"2.5\" rx=\"1\" fill=\"#fff\"/></svg>', 'color' => '#374151', 'bg' => '#f3f4f6', 'border' => '#d1d5db'],
]);


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

/**
 * Map PayMongo internal method keys to human-readable display names
 * used when saving to payments.payment_method and transactions.description.
 */
function format_payment_method(string $method): string
{
  return match (strtolower(trim($method))) {
    'gcash' => 'GCash',
    'paymaya', 'maya' => 'Maya',
    'card' => 'Card',
    'dob', 'online_banking', 'bank_transfer' => 'Bank Transfer',
    default => ucfirst($method) ?: 'PayMongo',
  };
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

  $cnt = $conn->prepare("SELECT COUNT(*) FROM invoices WHERE YEAR(issued_date) = ? AND MONTH(issued_date) = ?");
  $cnt->bind_param('ii', $issued_year, $issued_month);
  $cnt->execute();
  $cnt->bind_result($count);
  $cnt->fetch();
  $cnt->close();

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

/**
 * handle_send — creates one PayMongo payment link per payment method,
 * stores each link in paymongo_payments with its method, and emails
 * the tenant a button for every method.
 */
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

  // ── Generate one PayMongo payment link per method ─────────────────────
  $method_links = []; // ['method' => string, 'url' => string, 'link_id' => string]

  require_once __DIR__ . '/../../integrations/paymongo.php';
  $amount_int = (int) round($total_raw);

  if ($amount_int >= 1) {
    foreach (INVOICE_PAYMENT_METHODS as $method_key => $method_info) {
      try {
        // Re-use an existing active link for this invoice + method if available
        $exStmt = $conn->prepare("
                    SELECT paymongo_link_id, checkout_url
                    FROM paymongo_payments
                    WHERE reference_id   = ?
                      AND reference_type = 'invoice'
                      AND payment_method = ?
                      AND status NOT IN ('paid','expired','failed','cancelled')
                    ORDER BY created_at DESC LIMIT 1
                ");
        $exStmt->bind_param('is', $id, $method_key);
        $exStmt->execute();
        $exRow = $exStmt->get_result()->fetch_assoc();
        $exStmt->close();

        if ($exRow && !empty($exRow['checkout_url'])) {
          $method_links[] = [
            'method' => $method_key,
            'label' => $method_info['label'],
            'svg' => $method_info['svg'],
            'color' => $method_info['color'],
            'bg' => $method_info['bg'],
            'border' => $method_info['border'],
            'url' => $exRow['checkout_url'],
            'link_id' => $exRow['paymongo_link_id'],
          ];
          continue;
        }

        // Create payment — card uses Checkout Sessions (card-only), others use Links
        if ($method_key === 'card') {
          // ── PayMongo Checkout Session restricted to card only ──────────
          $secret = $_ENV['PAYMONGO_SECRET_KEY'] ?? getenv('PAYMONGO_SECRET_KEY');
          if (empty($secret))
            throw new \RuntimeException('PAYMONGO_SECRET_KEY not set.');

          $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
          $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
          $base = $scheme . '://' . $host;
          // Use a generic invoice success/cancel page (falls back to invoices page)
          $successUrl = $base . '/PropSight-Capstone/pages/user/invoice_payment_done.php?invoice_id=' . $id . '&method=card';
          $cancelUrl = $base . '/PropSight-Capstone/pages/user/invoice_payment_done.php?invoice_id=' . $id . '&method=card&cancelled=1';

          $csBody = [
            'data' => [
              'attributes' => [
                'send_email_receipt' => false,
                'show_description' => true,
                'show_line_items' => true,
                'description' => "PropSight Invoice {$invoice_no} — {$unit}",
                'line_items' => [
                  [
                    'currency' => 'PHP',
                    'amount' => $amount_int * 100,
                    'name' => "Invoice {$invoice_no}",
                    'quantity' => 1,
                  ]
                ],
                'payment_method_types' => ['card'],
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'metadata' => [
                  'invoice_id' => (string) $id,
                  'invoice_no' => $invoice_no,
                  'payment_method' => 'card',
                  'reference_type' => 'invoice',
                ],
              ],
            ],
          ];

          $ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
          $curlOpts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($csBody),
            CURLOPT_HTTPHEADER => [
              'Authorization: Basic ' . base64_encode($secret . ':'),
              'Content-Type: application/json',
              'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
          ];
          $caInfo = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');
          if ($caInfo && file_exists($caInfo)) {
            $curlOpts[CURLOPT_CAINFO] = $caInfo;
          } else {
            $defaultCa = __DIR__ . '/../../includes/ssl/cacert.pem';
            if (file_exists($defaultCa)) {
              $curlOpts[CURLOPT_CAINFO] = $defaultCa;
            } else {
              $curlOpts[CURLOPT_SSL_VERIFYPEER] = false;
              $curlOpts[CURLOPT_SSL_VERIFYHOST] = 0;
            }
          }
          curl_setopt_array($ch, $curlOpts);
          $csResponse = curl_exec($ch);
          $csHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
          curl_close($ch);

          $csDecoded = json_decode($csResponse, true);
          if ($csHttpCode >= 400 || empty($csDecoded['data'])) {
            $errMsg = $csDecoded['errors'][0]['detail'] ?? "Checkout Session error (HTTP {$csHttpCode})";
            throw new \RuntimeException($errMsg);
          }

          $checkout_url = $csDecoded['data']['attributes']['checkout_url'] ?? '';
          $link_id = $csDecoded['data']['id'] ?? null;
          $link_status = 'pending';

        } else {
          // ── PayMongo Link (GCash / Maya / Online Banking) ──────────────
          $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
          $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
          $redirectUrl = $scheme . '://' . $host
            . '/PropSight-Capstone/pages/user/invoice_payment_done.php'
            . '?invoice_id=' . $id . '&method=' . urlencode($method_key);

          $link = paymongo_create_link(
            $amount_int,
            "PropSight Invoice {$invoice_no} — {$unit} ({$method_info['label']})",
            ['invoice_id' => $id, 'invoice_no' => $invoice_no, 'payment_method' => $method_key],
            $redirectUrl
          );
          $checkout_url = $link['attributes']['checkout_url'] ?? '';
          $link_id = $link['id'] ?? null;
          $link_status = 'pending';
        }

        if (!empty($checkout_url)) {
          // Persist with payment_method so webhook/check_paid know what method was used
          $insStmt = $conn->prepare("
                        INSERT IGNORE INTO paymongo_payments
                            (booking_id, user_id, paymongo_link_id, checkout_url, amount, status,
                             payment_method, reference_id, reference_type, created_at, expires_at)
                        VALUES (NULL, NULL, ?, ?, ?, ?, ?, ?, 'invoice', NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR))
                    ");
          if ($insStmt) {
            $insStmt->bind_param('ssdssi', $link_id, $checkout_url, $total_raw, $link_status, $method_key, $id);
            $insStmt->execute();
            if ($insStmt->errno) {
              error_log('[invoice send] paymongo_payments INSERT error: ' . $insStmt->error);
            }
            $insStmt->close();
          }

          $method_links[] = [
            'method' => $method_key,
            'label' => $method_info['label'],
            'svg' => $method_info['svg'],
            'color' => $method_info['color'],
            'bg' => $method_info['bg'],
            'border' => $method_info['border'],
            'url' => $checkout_url,
            'link_id' => $link_id,
          ];
        }
      } catch (Throwable $pm_err) {
        error_log("[invoice send] PayMongo error for method {$method_key}: " . $pm_err->getMessage());
      }
    }
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
    $mail->Body = build_email_body($invoice_no, $name, $unit, $items, $total, $due, $status, $method_links);
    $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $mail->Body));

    $mail->send();

  } catch (Exception $e) {
    json_response(false, 'Email failed: ' . $e->getMessage());
  }

  $upd = $conn->prepare("UPDATE invoices SET status = 'Sent' WHERE id = ?");
  $upd->bind_param('i', $id);
  $upd->execute();

  // Return the first checkout URL for convenience (admin can copy it)
  $first_url = !empty($method_links) ? $method_links[0]['url'] : null;

  json_response(true, 'Invoice sent successfully.', [
    'invoice_id' => $id,
    'checkout_url' => $first_url,
    'method_links' => $method_links,
  ]);
}

/* ─── EMAIL BODY BUILDER ──────────────────────────────────────────────── */

/**
 * @param array $method_links  Each item: ['method'=>, 'label'=>, 'color'=>, 'bg'=>, 'border'=>, 'url'=>]
 */
function build_email_body(
  string $invoice_no,
  string $name,
  string $unit,
  string $items,
  string $total,
  string $due,
  string $status,
  array $method_links = []
): string {

  $paymongo_block = '';
  if (!empty($method_links)) {
    $buttons_html = '';
    foreach ($method_links as $ml) {
      $safe_url = htmlspecialchars($ml['url'], ENT_QUOTES);
      $safe_label = htmlspecialchars($ml['label']);
      $color = htmlspecialchars($ml['color']);
      $bg = htmlspecialchars($ml['bg']);
      $border = htmlspecialchars($ml['border']);

      $buttons_html .= <<<BTN
            <table role="presentation" cellpadding="0" cellspacing="0" style="display:inline-table;margin:5px 5px;">
              <tr>
                <td style="border-radius:10px;border:1.5px solid {$border};background:{$bg};
                            box-shadow:0 1px 4px rgba(0,0,0,.08);">
                  <a href="{$safe_url}" target="_blank"
                     style="display:block;padding:12px 22px;
                            font-family:Arial,sans-serif;font-size:13px;font-weight:700;
                            color:{$color};text-decoration:none;white-space:nowrap;
                            text-align:center;letter-spacing:.1px;">
                    Pay via {$safe_label}
                  </a>
                </td>
              </tr>
            </table>
BTN;
    }

    $paymongo_block = <<<PMBLOCK

          <!-- PayMongo Method Buttons -->
          <div style="margin-bottom:24px;">
            <p style="margin:0 0 14px;font-size:13px;color:#475569;font-weight:700;
                      text-align:center;text-transform:uppercase;letter-spacing:.6px;">
              Choose your payment method
            </p>
            <div style="text-align:center;">
              {$buttons_html}
            </div>
            <p style="margin:14px 0 0;font-size:11.5px;color:#94a3b8;text-align:center;line-height:1.6;">
              Each button opens a separate secure checkout.<br>
              Once paid through one, the remaining links expire automatically.
            </p>
            <p style="margin:6px 0 0;font-size:11px;color:#cbd5e1;text-align:center;">
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
          <!-- Important note -->
          <div style="background:#fffbeb;border-radius:9px;padding:13px 16px;margin-bottom:24px;">
            <div style="font-size:11px;font-weight:800;color:#92400e;margin-bottom:4px;letter-spacing:.5px;">IMPORTANT</div>
            <div style="font-size:12.5px;color:#92400e;line-height:1.65;">
              Use <strong>{$invoice_no}</strong> as your payment reference. After paying, please send your proof of payment via the <strong>Messages page</strong> in your account.
            </div>
          </div>

          {$paymongo_block}

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

  // Look up ALL active PayMongo links for this invoice (one per method)
  // NOTE: do NOT early-exit when status=Paid — payments/transactions may still be missing
  $pmStmt = $conn->prepare("
        SELECT id, paymongo_link_id, amount, status, payment_method
        FROM paymongo_payments
        WHERE reference_id   = ?
          AND reference_type = 'invoice'
        ORDER BY created_at DESC
    ");
  $pmStmt->bind_param('i', $id);
  $pmStmt->execute();
  $pmRows = $pmStmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $pmStmt->close();

  if (empty($pmRows)) {
    json_response(true, 'No payment link.', ['is_paid' => false]);
  }

  // Check if any already marked paid locally — still ensure payments/transactions are inserted
  foreach ($pmRows as $pmRow) {
    if ($pmRow['status'] === 'paid') {
      // Fix invoice status
      $fix = $conn->prepare("UPDATE invoices SET status = 'Paid' WHERE id = ? AND status != 'Paid'");
      $fix->bind_param('i', $id);
      $fix->execute();
      $fix->close();

      // Ensure payments row exists
      $pmtNote2 = 'INV-PMT-' . $id;
      $chk2 = $conn->prepare("SELECT payment_id FROM payments WHERE notes = ? LIMIT 1");
      $chk2->bind_param('s', $pmtNote2);
      $chk2->execute();
      $chk2->store_result();
      if ($chk2->num_rows === 0) {
        $chk2->close();
        $paidAmt2 = (float) $pmRow['amount'];
        $paidMeth2 = format_payment_method($pmRow['payment_method'] ?: 'PayMongo');
        $dateStr2 = date('Y-m-d');
        $pSt2 = 'paid';
        $ps2 = $conn->prepare("INSERT INTO payments (booking_id, payment_date, amount_paid, payment_method, payment_status, notes) VALUES (NULL, ?, ?, ?, ?, ?)");
        $ps2->bind_param('sdsss', $dateStr2, $paidAmt2, $paidMeth2, $pSt2, $pmtNote2);
        $ps2->execute();
        if ($ps2->errno) {
          error_log('[check_paid fast-path] payments INSERT error: ' . $ps2->error);
        }
        $ps2->close();
      } else {
        $chk2->close();
      }

      // Ensure transaction row exists
      $txRef2 = 'INV-PMT-' . $id;
      $txChk2 = $conn->prepare("SELECT id FROM transactions WHERE reference_no = ? LIMIT 1");
      $txChk2->bind_param('s', $txRef2);
      $txChk2->execute();
      $txChk2->store_result();
      if ($txChk2->num_rows === 0) {
        $txChk2->close();
        $paidAmt2    = (float) $pmRow['amount'];
        $paidMeth2   = format_payment_method($pmRow['payment_method'] ?: 'PayMongo');
        $dateStr2    = date('Y-m-d');
        $txDesc2     = 'PayMongo payment (' . $paidMeth2 . ') for Invoice #' . $id;
        $txCat2      = 'Invoice Revenue';
        $txTyp2      = 'Income';
        $txNotes2    = '';
        $txSt2 = $conn->prepare("INSERT INTO transactions (reference_no, description, category, type, amount, transaction_date, property_id, notes, recorded_by) VALUES (?, ?, ?, ?, ?, ?, NULL, ?, NULL)");
        $txSt2->bind_param('ssssdss', $txRef2, $txDesc2, $txCat2, $txTyp2, $paidAmt2, $dateStr2, $txNotes2);
        $txSt2->execute();
        if ($txSt2->errno) {
          error_log('[check_paid fast-path] transactions INSERT error: ' . $txSt2->error);
        }
        $txSt2->close();
      } else {
        $txChk2->close();
      }

      // Archive all OTHER pending PayMongo links for this invoice on the API side
      // so the tenant cannot pay again through the remaining email buttons
      $paidLinkId2 = $pmRow['paymongo_link_id'];
      $otherStmt2 = $conn->prepare("
          SELECT paymongo_link_id, payment_method FROM paymongo_payments
          WHERE  reference_id   = ?
            AND  reference_type = 'invoice'
            AND  paymongo_link_id != ?
            AND  status NOT IN ('paid','expired','failed','cancelled')
      ");
      $otherStmt2->bind_param('is', $id, $paidLinkId2);
      $otherStmt2->execute();
      $otherRows2 = $otherStmt2->get_result()->fetch_all(MYSQLI_ASSOC);
      $otherStmt2->close();

      if (!empty($otherRows2)) {
        require_once __DIR__ . '/../../integrations/paymongo.php';
        foreach ($otherRows2 as $other2) {
          if (!empty($other2['paymongo_link_id'])) {
            paymongo_archive_link($other2['paymongo_link_id'], $other2['payment_method']);
          }
        }
        $cancelOthers2 = $conn->prepare("
            UPDATE paymongo_payments
            SET    status = 'cancelled'
            WHERE  reference_id   = ?
              AND  reference_type = 'invoice'
              AND  paymongo_link_id != ?
              AND  status NOT IN ('paid','expired','failed','cancelled')
        ");
        $cancelOthers2->bind_param('is', $id, $paidLinkId2);
        $cancelOthers2->execute();
        $cancelOthers2->close();
      }

      // Invoice already Paid and records ensured — stop polling
      json_response(true, 'Synced.', ['is_paid' => true]);
    }
  }

  // No locally-paid row found — actively poll PayMongo for each link
  require_once __DIR__ . '/../../integrations/paymongo.php';

  $paid_method = null;
  $paid_link_id = null;
  $paid_amount = 0;
  $paid_pm_row = null;
  $paymentId = null;

  foreach ($pmRows as $pmRow) {
    if (empty($pmRow['paymongo_link_id']) || in_array($pmRow['status'], ['failed', 'expired', 'cancelled'])) {
      continue;
    }
    try {
      $isCard = ($pmRow['payment_method'] === 'card');

      if ($isCard) {
        // Card uses Checkout Sessions — different endpoint and status field
        $session = paymongo_request('GET', '/checkout_sessions/' . $pmRow['paymongo_link_id']);
        $csStatus = $session['attributes']['payment_intent']['attributes']['status'] ?? '';
        $isPaid = ($csStatus === 'succeeded');
        $paymentId = $session['attributes']['payment_intent']['attributes']['payments'][0]['id']
          ?? $session['attributes']['payment_intent']['id']
          ?? null;
      } else {
        // GCash / Maya / Online Banking use Links
        $session = paymongo_request('GET', '/links/' . $pmRow['paymongo_link_id']);
        $isPaid = (($session['attributes']['status'] ?? '') === 'paid');
        $paymentId = $session['attributes']['payments'][0]['id'] ?? null;
      }

      if ($isPaid) {
        $paid_method = format_payment_method($pmRow['payment_method'] ?: 'PayMongo');
        $paid_link_id = $pmRow['paymongo_link_id'];
        $paid_amount = (float) $pmRow['amount'];
        $paid_pm_row = $pmRow;
        break;
      }
    } catch (Throwable $e) {
      error_log('[check_paid] PayMongo poll failed for ' . $pmRow['paymongo_link_id'] . ': ' . $e->getMessage());
    }
  }

  if (!$paid_link_id) {
    json_response(true, 'Not paid yet.', ['is_paid' => false]);
  }

  // ── Payment confirmed — sync everything ───────────────────────────────
  $paidAt = gmdate('Y-m-d H:i:s');
  $date = gmdate('Y-m-d');

  // 1. Mark the paid paymongo_payments row
  $updPm = $conn->prepare("UPDATE paymongo_payments SET status='paid', paymongo_payment_id=?, paid_at=? WHERE paymongo_link_id=?");
  $updPm->bind_param('sss', $paymentId, $paidAt, $paid_link_id);
  $updPm->execute();
  $updPm->close();

  // 2. Expire all OTHER pending links for this invoice on PayMongo's side
  //    then cancel in DB — so the remaining email buttons stop working
  $otherRows = [];
  $otherSel = $conn->prepare("
      SELECT paymongo_link_id, payment_method FROM paymongo_payments
      WHERE  reference_id   = ?
        AND  reference_type = 'invoice'
        AND  paymongo_link_id != ?
        AND  status NOT IN ('paid','expired','failed','cancelled')
  ");
  $otherSel->bind_param('is', $id, $paid_link_id);
  $otherSel->execute();
  $otherRows = $otherSel->get_result()->fetch_all(MYSQLI_ASSOC);
  $otherSel->close();

  foreach ($otherRows as $other) {
    if (!empty($other['paymongo_link_id'])) {
      paymongo_archive_link($other['paymongo_link_id'], $other['payment_method']);
    }
  }

  $expStmt = $conn->prepare("
        UPDATE paymongo_payments
        SET    status = 'cancelled'
        WHERE  reference_id   = ?
          AND  reference_type = 'invoice'
          AND  paymongo_link_id != ?
          AND  status NOT IN ('paid','expired','failed','cancelled')
    ");
  $expStmt->bind_param('is', $id, $paid_link_id);
  $expStmt->execute();
  $expStmt->close();

  // 3. Mark invoice Paid
  $updInv = $conn->prepare("UPDATE invoices SET status='Paid' WHERE id=? AND status!='Paid'");
  $updInv->bind_param('i', $id);
  $updInv->execute();
  $updInv->close();

  // 4. Insert into payments table — now with the correct payment_method
  $pmtNote = 'INV-PMT-' . $id;
  $pmtCheck = $conn->prepare("SELECT payment_id FROM payments WHERE notes = ? LIMIT 1");
  $pmtCheck->bind_param('s', $pmtNote);
  $pmtCheck->execute();
  $pmtCheck->store_result();
  $pmtExists = $pmtCheck->num_rows > 0;
  $pmtCheck->close();

  if (!$pmtExists) {
    $pmtStatus = 'paid';
    $pmtStmt = $conn->prepare("INSERT INTO payments (booking_id, payment_date, amount_paid, payment_method, payment_status, notes) VALUES (NULL, ?, ?, ?, ?, ?)");
    $pmtStmt->bind_param('sdsss', $date, $paid_amount, $paid_method, $pmtStatus, $pmtNote);
    $pmtStmt->execute();
    if ($pmtStmt->errno) {
      error_log('[check_paid] payments INSERT error: ' . $pmtStmt->error);
    }
    $pmtStmt->close();
  }

  // 5. Log transaction — with correct payment method in description
  $invRef = 'INV-PMT-' . $id;
  $txCheck = $conn->prepare("SELECT id FROM transactions WHERE reference_no = ? LIMIT 1");
  $txCheck->bind_param('s', $invRef);
  $txCheck->execute();
  $txCheck->store_result();
  $txExists = $txCheck->num_rows > 0;
  $txCheck->close();

  if (!$txExists) {
    $desc = 'PayMongo payment (' . $paid_method . ') for Invoice #' . $id;
    $cat = 'Invoice Revenue';
    $typ = 'Income';
    $notes = '';

    $txStmt = $conn->prepare("INSERT INTO transactions (reference_no, description, category, type, amount, transaction_date, property_id, notes, recorded_by) VALUES (?, ?, ?, ?, ?, ?, NULL, ?, NULL)");
    $txStmt->bind_param('ssssdss', $invRef, $desc, $cat, $typ, $paid_amount, $date, $notes);
    $txStmt->execute();
    if ($txStmt->errno) {
      error_log('[check_paid] transactions INSERT error: ' . $txStmt->error);
    }
    $txStmt->close();
  }

  json_response(true, 'Payment confirmed.', ['is_paid' => true, 'payment_method' => $paid_method]);
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