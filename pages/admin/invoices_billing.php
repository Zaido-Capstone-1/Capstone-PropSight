<?php
include '../../includes/session.php';

if ($_SESSION['role'] !== 'admin') {
  echo '<!DOCTYPE html>
<html>
<head><meta http-equiv="refresh" content="2;url=javascript:history.back()"></head>
<body></body>
</html>';
  exit;
}

$page_title = 'Invoices / Billing';
$active_page = 'invoices_billing';

include '../../includes/session.php';
include '../../includes/db.php';
include '../../includes/layout_open.php';

$statsResult = mysqli_query($conn, "
    SELECT
        COUNT(*)                AS total,
        SUM(status = 'Paid')    AS paid,
        SUM(status = 'Pending') AS pending,
        SUM(status = 'Overdue') AS overdue
    FROM invoices
");
$stats = mysqli_fetch_assoc($statsResult);

$allResult = mysqli_query($conn, "
    SELECT
        i.id,
        i.invoice_no,
        t.full_name                              AS tenant_name,
        t.email                                  AS tenant_email,
        i.unit,
        DATE_FORMAT(i.issued_date, '%b %d, %Y') AS issued_label,
        DATE_FORMAT(i.due_date,    '%b %d, %Y') AS due_label,
        i.items,
        i.total,
        i.status
    FROM invoices i
    LEFT JOIN tenants t ON t.tenant_id = i.tenant_id
    ORDER BY i.issued_date DESC
");
$invoices = mysqli_fetch_all($allResult, MYSQLI_ASSOC);

$tenantsResult = mysqli_query($conn, "
    SELECT t.tenant_id, t.full_name, u.unit_number, u.unit_name
    FROM tenants t
    LEFT JOIN units u ON u.tenant_id = t.tenant_id
    ORDER BY t.full_name ASC
");
$tenants = mysqli_fetch_all($tenantsResult, MYSQLI_ASSOC);

function badge_class(string $status): string
{
  return match ($status) {
    'Paid' => 'success',
    'Pending' => 'warning',
    'Overdue' => 'danger',
    'Sent' => 'info',
    default => 'warning',
  };
}

$inv_per_page = 10;
$inv_total_records = count($invoices);
$inv_total_pages = ceil($inv_total_records / $inv_per_page);
$inv_page = isset($_GET['inv_page']) ? max(1, min((int) $_GET['inv_page'], max(1, $inv_total_pages))) : 1;
$inv_offset = ($inv_page - 1) * $inv_per_page;
$inv_invoices = array_slice($invoices, $inv_offset, $inv_per_page);
?>

<link rel="stylesheet" href="../../assets/css/admin-css/invoice_billings.css">

<div class="page-inner">

  <div class="inv-page-header">
    <div>
      <h2>Invoices &amp; Billing</h2>
      <p>Generate, send, and track invoices for all tenants.</p>
    </div>
    <button class="inv-new-btn" id="openNewInvoiceBtn">
      <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <line x1="12" y1="5" x2="12" y2="19" />
        <line x1="5" y1="12" x2="19" y2="12" />
      </svg>
      New Invoice
    </button>
  </div>

  <div class="inv-stat-grid">

    <div class="inv-stat-card">
      <div>
        <div class="inv-stat-label">Total Invoices</div>
        <div class="inv-stat-value" id="stat-total"><?= (int) $stats['total'] ?></div>
        <div class="inv-stat-sub">All time</div>
      </div>
      <div class="inv-stat-icon blue">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
          <polyline points="14 2 14 8 20 8" />
        </svg>
      </div>
    </div>

    <div class="inv-stat-card">
      <div>
        <div class="inv-stat-label">Paid</div>
        <div class="inv-stat-value" id="stat-paid"><?= (int) $stats['paid'] ?></div>
        <div class="inv-stat-sub">All time</div>
      </div>
      <div class="inv-stat-icon green">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
          <polyline points="22 4 12 14.01 9 11.01" />
        </svg>
      </div>
    </div>

    <div class="inv-stat-card">
      <div>
        <div class="inv-stat-label">Pending</div>
        <div class="inv-stat-value" id="stat-pending"><?= (int) $stats['pending'] ?></div>
        <div class="inv-stat-sub">All time</div>
      </div>
      <div class="inv-stat-icon amber">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="10" />
          <polyline points="12 6 12 12 16 14" />
        </svg>
      </div>
    </div>

    <div class="inv-stat-card">
      <div>
        <div class="inv-stat-label">Overdue</div>
        <div class="inv-stat-value" id="stat-overdue"><?= (int) $stats['overdue'] ?></div>
        <div class="inv-stat-sub">All time</div>
      </div>
      <div class="inv-stat-icon red">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="10" />
          <line x1="15" y1="9" x2="9" y2="15" />
          <line x1="9" y1="9" x2="15" y2="15" />
        </svg>
      </div>
    </div>

  </div>

  <div class="inv-card">

    <div class="inv-card-top">
      <span class="inv-card-title">Invoice List</span>
      <div class="inv-filters">
        <div class="inv-search">
          <input type="text" id="searchFilter" placeholder="Search tenant or invoice" autocomplete="off">
        </div>
        <select class="inv-select" id="statusFilter">
          <option value="">All Status</option>
          <option value="Paid">Paid</option>
          <option value="Pending">Pending</option>
          <option value="Overdue">Overdue</option>
          <option value="Sent">Sent</option>
        </select>
      </div>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Invoice #</th>
            <th>Tenant</th>
            <th>Unit</th>
            <th>Issued</th>
            <th>Due</th>
            <th>Items</th>
            <th>Total</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="invoiceTableBody">
          <?php if (empty($inv_invoices)): ?>
            <tr id="noDataRow">
              <td colspan="9" style="text-align:center;padding:48px;color:#aab;">
                <?= empty($invoices) ? 'No invoices yet. Create your first one!' : 'No invoices on this page.' ?>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($inv_invoices as $inv):
              $unit = !empty($inv['unit']) ? $inv['unit'] : '—';
              $bc = badge_class($inv['status']);
              ?>
              <tr data-id="<?= (int) $inv['id'] ?>" data-status="<?= htmlspecialchars($inv['status']) ?>"
                data-search="<?= strtolower(htmlspecialchars($inv['invoice_no'] . ' ' . $inv['tenant_name'] . ' ' . $unit)) ?>"
                data-inv='<?= htmlspecialchars(json_encode([
                  'id' => $inv['id'],
                  'invoice_no' => $inv['invoice_no'],
                  'tenant' => $inv['tenant_name'],
                  'email' => $inv['tenant_email'],
                  'unit' => $unit,
                  'items' => $inv['items'],
                  'total' => $inv['total'],
                  'issued' => $inv['issued_label'],
                  'due' => $inv['due_label'],
                  'status' => $inv['status'],
                ]), ENT_QUOTES) ?>'>
                <td class="td-no"><?= htmlspecialchars($inv['invoice_no'] ?? '—') ?></td>
                <td class="td-name"><?= htmlspecialchars($inv['tenant_name'] ?? '—') ?></td>
                <td class="td-soft"><?= htmlspecialchars($unit) ?></td>
                <td class="td-soft"><?= htmlspecialchars($inv['issued_label']) ?></td>
                <td class="td-soft"><?= htmlspecialchars($inv['due_label']) ?></td>
                <td class="td-items" title="<?= htmlspecialchars($inv['items']) ?>"><?= htmlspecialchars($inv['items']) ?>
                </td>
                <td class="td-total">₱ <?= number_format((float) $inv['total'], 2) ?></td>
                <td><span class="inv-badge <?= $bc ?>"><?= htmlspecialchars($inv['status']) ?></span></td>
                <td>
                  <div class="inv-actions">
                    <button class="inv-btn secondary view-btn" data-id="<?= (int) $inv['id'] ?>">View</button>
                    <button class="inv-btn primary send-btn" data-id="<?= (int) $inv['id'] ?>">Send</button>
                    <button class="inv-btn ghost  more-btn" data-id="<?= (int) $inv['id'] ?>" title="More">⋯</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
      <?php if ($inv_total_pages > 1): ?>
        <div
          style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-top:1px solid var(--border);">
          <div style="font-size:13px;color:var(--text-soft);">
            Showing <?= $inv_offset + 1 ?> - <?= min($inv_offset + $inv_per_page, $inv_total_records) ?> of
            <?= $inv_total_records ?>
          </div>
          <div style="display:flex;gap:4px;">
            <?php if ($inv_page > 1): ?>
              <a href="?inv_page=<?= $inv_page - 1 ?>"
                style="padding:6px 12px;border:1.5px solid var(--border);border-radius:var(--radius);font-size:13px;text-decoration:none;color:var(--text);">‹
                Prev</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $inv_total_pages; $i++): ?>
              <a href="?inv_page=<?= $i ?>"
                style="padding:6px 12px;border:1.5px solid var(--border);border-radius:var(--radius);font-size:13px;text-decoration:none;background:<?= $i === $inv_page ? 'var(--primary)' : 'transparent' ?>;color:<?= $i === $inv_page ? 'white' : 'var(--text)' ?>;"><?= $i ?></a>
            <?php endfor; ?>

            <?php if ($inv_page < $inv_total_pages): ?>
              <a href="?inv_page=<?= $inv_page + 1 ?>"
                style="padding:6px 12px;border:1.5px solid var(--border);border-radius:var(--radius);font-size:13px;text-decoration:none;color:var(--text);">Next
                ›</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <div class="inv-empty" id="emptyState">
        <svg width="38" height="38" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
          <circle cx="11" cy="11" r="8" />
          <line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
        <p>No invoices match your filters.</p>
      </div>
    </div>

  </div>

  <div id="newInvoiceModal" class="inv-overlay" role="dialog" aria-modal="true" aria-labelledby="newInvTitle">
    <div class="inv-modal">
      <div class="inv-modal-head">
        <span class="inv-modal-title" id="newInvTitle">New Invoice</span>
        <button class="inv-modal-close" id="closeNewInvoice" aria-label="Close">✕</button>
      </div>
      <div class="inv-modal-body">
        <form id="newInvoiceForm" novalidate>

          <div class="inv-form-row">
            <div class="inv-form-group">
              <label for="f_tenant">Tenant <span class="req">*</span></label>
              <select id="f_tenant" name="tenant_id" required>
                <option value="">Select tenant…</option>
                <?php foreach ($tenants as $t): ?>
                  <option value="<?= (int) $t['tenant_id'] ?>"
                    data-unit="<?= htmlspecialchars($t['unit_number'] ?? $t['unit_name'] ?? '') ?>">
                    <?= htmlspecialchars($t['full_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="inv-form-group">
              <label for="f_unit">Unit <span class="req">*</span></label>
              <input id="f_unit" type="text" name="unit" placeholder="e.g. A-101" required>
            </div>
          </div>

          <div class="inv-form-row">
            <div class="inv-form-group">
              <label for="f_issued">Issued Date <span class="req">*</span></label>
              <input id="f_issued" type="date" name="issued_date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="inv-form-group">
              <label for="f_due">Due Date <span class="req">*</span></label>
              <input id="f_due" type="date" name="due_date" required>
            </div>
          </div>

          <div class="inv-form-row single">
            <div class="inv-form-group">
              <label for="f_items">Items / Description <span class="req">*</span></label>
              <input id="f_items" type="text" name="items" placeholder="e.g. Monthly Rent + Water Bill" required>
            </div>
          </div>

          <div class="inv-form-row">
            <div class="inv-form-group">
              <label for="f_total">Total Amount (₱) <span class="req">*</span></label>
              <input id="f_total" type="number" name="total" min="0" step="0.01" placeholder="0.00" required>
            </div>
            <div class="inv-form-group">
              <label for="f_status">Status</label>
              <select id="f_status" name="status">
                <option value="Pending">Pending</option>
                <option value="Paid">Paid</option>
                <option value="Overdue">Overdue</option>
              </select>
            </div>
          </div>

          <div class="inv-modal-foot" style="padding:0;border:none;margin-top:6px;">
            <button type="button" class="inv-btn secondary" id="cancelNewInvoice">Cancel</button>
            <button type="submit" class="inv-btn primary" id="submitNewInvoice">
              <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
              </svg>
              Create Invoice
            </button>
          </div>

        </form>
      </div>
    </div>
  </div>

  <div id="viewInvoiceModal" class="inv-overlay" role="dialog" aria-modal="true" aria-labelledby="viewInvTitle">
    <div class="inv-modal lg">
      <div class="inv-modal-head">
        <span class="inv-modal-title" id="viewInvTitle">Invoice Details</span>
        <button class="inv-modal-close" id="closeViewInvoice" aria-label="Close">✕</button>
      </div>
      <div class="inv-modal-body">

        <div class="inv-view-header">
          <div class="inv-view-no">Invoice</div>
          <div class="inv-view-title" id="vi_invoice_no">—</div>
          <div class="inv-view-status">
            <span class="inv-badge" id="vi_badge">—</span>
          </div>
        </div>

        <div class="inv-detail-grid">
          <div class="inv-detail-item">
            <div class="inv-detail-label">Tenant</div>
            <div class="inv-detail-value" id="vi_tenant">—</div>
          </div>
          <div class="inv-detail-item">
            <div class="inv-detail-label">Email</div>
            <div class="inv-detail-value email" id="vi_email">—</div>
          </div>
          <div class="inv-detail-item">
            <div class="inv-detail-label">Unit</div>
            <div class="inv-detail-value" id="vi_unit">—</div>
          </div>
          <div class="inv-detail-item">
            <div class="inv-detail-label">Total Amount</div>
            <div class="inv-detail-value big" id="vi_total">—</div>
          </div>
          <div class="inv-detail-item">
            <div class="inv-detail-label">Issued</div>
            <div class="inv-detail-value" id="vi_issued">—</div>
          </div>
          <div class="inv-detail-item">
            <div class="inv-detail-label">Due Date</div>
            <div class="inv-detail-value" id="vi_due">—</div>
          </div>
        </div>

        <div class="inv-items-box">
          <div class="inv-items-box-label">Items / Description</div>
          <div class="inv-items-box-value" id="vi_items">—</div>
        </div>

      </div>
      <div class="inv-modal-foot">
        <button type="button" class="inv-btn secondary" id="closeViewInvoice2">Close</button>
        <button type="button" class="inv-btn primary" id="vi_sendBtn">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <line x1="22" y1="2" x2="11" y2="13" />
            <polygon points="22 2 15 22 11 13 2 9 22 2" />
          </svg>
          Send Invoice
        </button>
      </div>
    </div>
  </div>

  <div id="sendModal" class="inv-overlay" role="dialog" aria-modal="true" aria-labelledby="sendModalTitle">
    <div class="inv-modal sm">
      <div class="inv-modal-head">
        <span class="inv-modal-title" id="sendModalTitle">Send Invoice</span>
        <button class="inv-modal-close" id="closeSendModal" aria-label="Close">✕</button>
      </div>
      <div class="inv-modal-body">

        <div id="sendModalLoading" class="inv-spinner-wrap">
          <div class="inv-spinner"></div> Loading invoice…
        </div>

        <div id="sendModalContent" style="display:none;">
          <p class="send-hint">Review the details below before sending the email to the tenant.</p>
          <table class="send-preview">
            <tr>
              <th>Invoice #</th>
              <td id="si_invoice_no"></td>
            </tr>
            <tr>
              <th>Tenant</th>
              <td id="si_tenant"></td>
            </tr>
            <tr>
              <th>Email</th>
              <td id="si_email" class="sp-email"></td>
            </tr>
            <tr>
              <th>Unit</th>
              <td id="si_unit"></td>
            </tr>
            <tr>
              <th>Items</th>
              <td id="si_items"></td>
            </tr>
            <tr>
              <th>Total</th>
              <td id="si_total" class="sp-total"></td>
            </tr>
            <tr>
              <th>Due Date</th>
              <td id="si_due"></td>
            </tr>
          </table>
          <div id="sendModalError" class="send-error"></div>
        </div>

      </div>
      <div class="inv-modal-foot">
        <button type="button" class="inv-btn secondary" id="cancelSendModal">Cancel</button>
        <button type="button" class="inv-btn primary" id="confirmSendBtn" style="display:none;">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <line x1="22" y1="2" x2="11" y2="13" />
            <polygon points="22 2 15 22 11 13 2 9 22 2" />
          </svg>
          Send Invoice
        </button>
      </div>
    </div>
  </div>

  <div id="statusDropdown" class="inv-dropdown" role="menu">
    <button role="menuitem" data-status="Paid">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
        <polyline points="22 4 12 14.01 9 11.01" />
      </svg>
      Mark as Paid
    </button>
    <button role="menuitem" data-status="Pending">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="10" />
        <polyline points="12 6 12 12 16 14" />
      </svg>
      Mark as Pending
    </button>
    <button role="menuitem" data-status="Overdue">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="10" />
        <line x1="12" y1="8" x2="12" y2="12" />
        <line x1="12" y1="16" x2="12.01" y2="16" />
      </svg>
      Mark as Overdue
    </button>
    <hr>
    <button role="menuitem" class="dd-danger" id="deleteInvoiceBtn">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <polyline points="3 6 5 6 21 6" />
        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
        <path d="M10 11v6" />
        <path d="M14 11v6" />
      </svg>
      Delete Invoice
    </button>
  </div>

  <script>
    window.PS_CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
  </script>
  <script src="../../assets/js/admin/invoice_billings.js"></script>

</div><!-- /page-inner -->
<?php include '../../includes/layout_close.php'; ?>