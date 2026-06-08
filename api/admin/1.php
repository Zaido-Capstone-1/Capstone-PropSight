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

define('SMTP_HOST', MAIL_HOST);
define('SMTP_PORT', (int) MAIL_PORT);
define('SMTP_SECURE', PHPMailer::ENCRYPTION_STARTTLS);
define('SMTP_USER', MAIL_USERNAME);
define('SMTP_PASS', MAIL_PASSWORD);
define('MAIL_FROM', MAIL_FROM_EMAIL);
define('INV_MAIL_FROM_NAME', MAIL_FROM_NAME);
define('CAFILE_PATH', realpath(__DIR__ . '/../../includes/ssl/cacert.pem'));

define('INVOICE_PAYMENT_METHODS', [
    'gcash' => [
        'label' => 'GCash',
        'color' => '#0055cc',
        'bg' => '#e8f0ff',
        'border' => '#b3cbf7',
    ],
    'paymaya' => [
        'label' => 'Maya',
        'color' => '#006b35',
        'bg' => '#e6f9ef',
        'border' => '#a3dfc0',
    ],
    'card' => [
        'label' => 'Credit / Debit Card',
        'color' => '#1e40af',
        'bg' => '#eef2ff',
        'border' => '#a5b4fc',
    ],
    'dob' => [
        'label' => 'Online Banking',
        'color' => '#166534',
        'bg' => '#f0fdf4',
        'border' => '#86efac',
    ],
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

$action = trim($_POST['action'] ?? '');
if (!$action)
    json_response(false, 'No action provided.');

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

    if (!$stmt->execute())
        json_response(false, 'Failed to create invoice: ' . $stmt->error);

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
    if (!$stmt->execute())
        json_response(false, 'Failed to update status: ' . $stmt->error);

    json_response(true, 'Status updated.');
}


function handle_delete(mysqli $conn): void
{
    $id = (int) ($_POST['id'] ?? 0);
    if (!$id)
        json_response(false, 'Missing invoice ID.');

    $stmt = $conn->prepare('DELETE FROM invoices WHERE id = ?');
    $stmt->bind_param('i', $id);
    if (!$stmt->execute())
        json_response(false, 'Failed to delete invoice: ' . $stmt->error);

    json_response(true, 'Invoice deleted.');
}


function handle_get_invoice(mysqli $conn): void
{
    $id = (int) ($_POST['id'] ?? 0);
    if (!$id)
        json_response(false, 'Missing invoice ID.');

    $stmt = $conn->prepare("
    SELECT
      i.id, i.invoice_no, i.unit, i.items, i.total, i.status, i.issued_date,
      DATE_FORMAT(i.issued_date, '%b %d, %Y') AS issued_label,
      DATE_FORMAT(i.due_date,    '%b %d, %Y') AS due_label,
      t.full_name, t.email
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

    $method_links = [];

    require_once __DIR__ . '/../../includes/paymongo.php';
    $amount_int = (int) round($total_raw);

    if ($amount_int >= 1) {
        foreach (INVOICE_PAYMENT_METHODS as $method_key => $method_info) {
            try {
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
                        'color' => $method_info['color'],
                        'bg' => $method_info['bg'],
                        'border' => $method_info['border'],
                        'url' => $exRow['checkout_url'],
                        'link_id' => $exRow['paymongo_link_id'],
                    ];
                    continue;
                }

                if ($method_key === 'card') {
                    $secret = $_ENV['PAYMONGO_SECRET_KEY'] ?? getenv('PAYMONGO_SECRET_KEY');
                    if (empty($secret))
                        throw new \RuntimeException('PAYMONGO_SECRET_KEY not set.');

                    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $base = $scheme . '://' . $host;
                    $successUrl = $base . '/PropSight-Capstone/pages/admin/invoices_billing.php?card_paid=1&inv=' . $id;
                    $cancelUrl = $base . '/PropSight-Capstone/pages/admin/invoices_billing.php?card_cancelled=1&inv=' . $id;

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
                            ]
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
                    $link = paymongo_create_link(
                        $amount_int,
                        "PropSight Invoice {$invoice_no} — {$unit} ({$method_info['label']})",
                        ['invoice_id' => $id, 'invoice_no' => $invoice_no, 'payment_method' => $method_key]
                    );
                    $checkout_url = $link['attributes']['checkout_url'] ?? '';
                    $link_id = $link['id'] ?? null;
                    $link_status = 'pending';
                }

                if (!empty($checkout_url)) {
                    $insStmt = $conn->prepare("
            INSERT IGNORE INTO paymongo_payments
              (booking_id, user_id, paymongo_link_id, checkout_url, amount, status,
               payment_method, reference_id, reference_type, created_at, expires_at)
            VALUES (0, 0, ?, ?, ?, ?, ?, ?, 'invoice', NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR))
          ");
                    if ($insStmt) {
                        $insStmt->bind_param('ssdssi', $link_id, $checkout_url, $total_raw, $link_status, $method_key, $id);
                        $insStmt->execute();
                        $insStmt->close();
                    }

                    $method_links[] = [
                        'method' => $method_key,
                        'label' => $method_info['label'],
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

    $first_url = !empty($method_links) ? $method_links[0]['url'] : null;
    json_response(true, 'Invoice sent successfully.', [
        'invoice_id' => $id,
        'checkout_url' => $first_url,
        'method_links' => $method_links,
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
    array $method_links = []
): string {

    // ── Pure CSS icons — no images, no SVG, works in Gmail/Outlook/Yahoo ──
    $icon_map = [
        'gcash' =>
            '<span style="display:inline-block;width:26px;height:26px;line-height:26px;' .
            'text-align:center;border-radius:50%;background:#007DFE;' .
            'font-family:Arial Black,Arial,sans-serif;font-weight:900;font-size:14px;' .
            'color:#ffffff;vertical-align:middle;mso-line-height-rule:exactly;">G</span>',

        'paymaya' =>
            '<span style="display:inline-block;padding:3px 7px;border-radius:5px;' .
            'background:#00A550;font-family:Georgia,serif;font-style:italic;' .
            'font-weight:bold;font-size:13px;color:#ffffff;' .
            'vertical-align:middle;line-height:20px;mso-line-height-rule:exactly;">maya</span>',

        'card' =>
            '<span style="display:inline-block;width:28px;height:20px;line-height:20px;' .
            'border-radius:4px;background:#1A1F71;text-align:center;' .
            'font-size:13px;color:#ffffff;vertical-align:middle;' .
            'mso-line-height-rule:exactly;">&#9646;&#9646;</span>',

        'dob' =>
            '<span style="display:inline-block;width:26px;height:26px;line-height:26px;' .
            'text-align:center;border-radius:5px;background:#1D4ED8;' .
            'font-size:15px;color:#ffffff;vertical-align:middle;' .
            'mso-line-height-rule:exactly;">&#127968;</span>',
    ];

    // ── Build 2-column table of payment buttons ────────────────────────────
    $paymongo_block = '';
    if (!empty($method_links)) {

        $pairs = array_chunk($method_links, 2);
        $rows_html = '';

        foreach ($pairs as $pair) {
            $rows_html .= '<tr>';
            foreach ($pair as $ml) {
                $safe_url = htmlspecialchars($ml['url'], ENT_QUOTES);
                $safe_label = htmlspecialchars($ml['label']);
                $color = htmlspecialchars($ml['color']);
                $bg = htmlspecialchars($ml['bg']);
                $border = htmlspecialchars($ml['border']);
                $icon_html = $icon_map[$ml['method']] ?? '';

                $rows_html .= <<<CELL
          <td style="padding:5px;width:50%;">
            <a href="{$safe_url}" target="_blank"
               style="display:block;padding:12px 14px;
                      background:{$bg};
                      border-radius:10px;text-decoration:none;
                      border:1.5px solid {$border};
                      box-shadow:0 1px 3px rgba(0,0,0,.08);
                      text-align:center;white-space:nowrap;">
              {$icon_html}&nbsp;&nbsp;<span style="color:{$color};font-family:Arial,sans-serif;font-size:13px;font-weight:700;vertical-align:middle;">Pay via {$safe_label}</span>
            </a>
          </td>
CELL;
            }
            // Pad odd row
            if (count($pair) === 1) {
                $rows_html .= '<td style="padding:5px;width:50%;"></td>';
            }
            $rows_html .= '</tr>';
        }

        $paymongo_block = <<<PMBLOCK
      <div style="margin-bottom:24px;">
        <p style="margin:0 0 14px;font-size:13px;color:#475569;font-weight:700;
                  text-align:center;text-transform:uppercase;letter-spacing:.6px;
                  font-family:Arial,sans-serif;">
          Choose your payment method
        </p>
        <table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">
          {$rows_html}
        </table>
        <p style="margin:14px 0 0;font-size:11.5px;color:#94a3b8;text-align:center;
                  line-height:1.6;font-family:Arial,sans-serif;">
          Each button opens a separate secure checkout.<br>
          Once paid through one, the remaining links expire automatically.
        </p>
        <p style="margin:6px 0 0;font-size:11px;color:#cbd5e1;text-align:center;
                  font-family:Arial,sans-serif;">
          &#128274;&nbsp; Powered by PayMongo &middot; Secure &amp; encrypted
        </p>
      </div>
PMBLOCK;
    }

    $year = date('Y');

    return <<<HTML
<div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;background:#f8fafc;padding:32px 16px;">
  <div style="background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;">

    <!-- Header -->
    <div style="background:#1e40af;padding:28px 32px;">
      <div style="font-size:10px;font-weight:700;letter-spacing:3px;color:rgba(255,255,255,.65);text-transform:uppercase;margin-bottom:6px;">Invoice</div>
      <div style="font-size:24px;font-weight:800;color:#ffffff;">{$invoice_no}</div>
      <div style="margin-top:10px;display:inline-block;padding:3px 12px;border-radius:20px;
                  background:rgba(255,255,255,.15);font-size:11px;font-weight:700;
                  color:rgba(255,255,255,.9);letter-spacing:.8px;text-transform:uppercase;">
        {$status}
      </div>
    </div>

    <div style="padding:28px 32px;">

      <p style="margin:0 0 6px;font-size:15px;color:#1e2533;font-weight:600;">Hello, {$name}</p>
      <p style="margin:0 0 24px;font-size:13.5px;color:#64748b;line-height:1.65;">
        An invoice has been issued for your accommodation. Please review the details below and settle your balance on or before the due date.
      </p>

      <!-- Invoice details table -->
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


/* ─── CHECK PAID ──────────────────────────────────────────────────────── */

function handle_check_paid(mysqli $conn): void
{
    $id = (int) ($_POST['id'] ?? 0);
    if (!$id)
        json_response(false, 'Missing invoice ID.');

    $inv = $conn->prepare("SELECT status FROM invoices WHERE id = ? LIMIT 1");
    $inv->bind_param('i', $id);
    $inv->execute();
    $row = $inv->get_result()->fetch_assoc();
    $inv->close();

    if (!$row)
        json_response(false, 'Invoice not found.');
    if ($row['status'] === 'Paid')
        json_response(true, 'Already paid.', ['is_paid' => true]);

    $pmStmt = $conn->prepare("
    SELECT id, paymongo_link_id, amount, status, payment_method
    FROM paymongo_payments
    WHERE reference_id = ? AND reference_type = 'invoice'
    ORDER BY created_at DESC
  ");
    $pmStmt->bind_param('i', $id);
    $pmStmt->execute();
    $pmRows = $pmStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $pmStmt->close();

    if (empty($pmRows))
        json_response(true, 'No payment link.', ['is_paid' => false]);

    foreach ($pmRows as $pmRow) {
        if ($pmRow['status'] === 'paid') {
            $fix = $conn->prepare("UPDATE invoices SET status = 'Paid' WHERE id = ? AND status != 'Paid'");
            $fix->bind_param('i', $id);
            $fix->execute();
            $fix->close();
            json_response(true, 'Synced.', ['is_paid' => true]);
        }
    }

    require_once __DIR__ . '/../../includes/paymongo.php';

    $paid_method = null;
    $paid_link_id = null;
    $paid_amount = 0;
    $paymentId = null;

    foreach ($pmRows as $pmRow) {
        if (empty($pmRow['paymongo_link_id']) || in_array($pmRow['status'], ['failed', 'expired', 'cancelled']))
            continue;
        try {
            $link = paymongo_request('GET', '/links/' . $pmRow['paymongo_link_id']);
            $linkStatus = $link['attributes']['status'] ?? '';
            if ($linkStatus === 'paid') {
                $paid_method = $pmRow['payment_method'] ?: 'PayMongo';
                $paid_link_id = $pmRow['paymongo_link_id'];
                $paid_amount = (float) $pmRow['amount'];
                $paymentId = $link['attributes']['payments'][0]['id'] ?? null;
                break;
            }
        } catch (Throwable $e) {
            error_log('[check_paid] PayMongo poll failed for link ' . $pmRow['paymongo_link_id'] . ': ' . $e->getMessage());
        }
    }

    if (!$paid_link_id)
        json_response(true, 'Not paid yet.', ['is_paid' => false]);

    $paidAt = date('Y-m-d H:i:s');
    $date = date('Y-m-d');

    $updPm = $conn->prepare("UPDATE paymongo_payments SET status='paid', paymongo_payment_id=?, paid_at=? WHERE paymongo_link_id=?");
    $updPm->bind_param('sss', $paymentId, $paidAt, $paid_link_id);
    $updPm->execute();
    $updPm->close();

    $expStmt = $conn->prepare("
    UPDATE paymongo_payments SET status = 'cancelled'
    WHERE reference_id = ? AND reference_type = 'invoice'
      AND paymongo_link_id != ?
      AND status NOT IN ('paid','expired','failed','cancelled')
  ");
    $expStmt->bind_param('is', $id, $paid_link_id);
    $expStmt->execute();
    $expStmt->close();

    $updInv = $conn->prepare("UPDATE invoices SET status='Paid' WHERE id=? AND status!='Paid'");
    $updInv->bind_param('i', $id);
    $updInv->execute();
    $updInv->close();

    $pmtNote = 'INV-PMT-' . $id;
    $pmtCheck = $conn->prepare("SELECT payment_id FROM payments WHERE notes = ? LIMIT 1");
    $pmtCheck->bind_param('s', $pmtNote);
    $pmtCheck->execute();
    $pmtCheck->store_result();
    $pmtExists = $pmtCheck->num_rows > 0;
    $pmtCheck->close();

    if (!$pmtExists) {
        $pmtStatus = 'paid';
        $pmtStmt = $conn->prepare("INSERT INTO payments (booking_id, payment_date, amount_paid, payment_method, payment_status, notes) VALUES (0, ?, ?, ?, ?, ?)");
        $pmtStmt->bind_param('sdsss', $date, $paid_amount, $paid_method, $pmtStatus, $pmtNote);
        $pmtStmt->execute();
        $pmtStmt->close();
    }

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
        $recBy = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $safeRef = $conn->real_escape_string($invRef);
        $safeDesc = $conn->real_escape_string($desc);
        $safeCat = $conn->real_escape_string($cat);
        $safeNotes = $conn->real_escape_string($notes);
        $recBySQL = $recBy !== null ? (int) $recBy : 'NULL';
        $conn->query("INSERT INTO transactions (reference_no, description, category, type, amount, transaction_date, property_id, notes, recorded_by)
                  VALUES ('$safeRef','$safeDesc','$safeCat','$typ',$paid_amount,'$date',NULL,'$safeNotes',$recBySQL)");
    }

    json_response(true, 'Payment confirmed.', ['is_paid' => true, 'payment_method' => $paid_method]);
}


/* ─── GET STATS ───────────────────────────────────────────────────────── */

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