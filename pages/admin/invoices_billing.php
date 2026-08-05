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
include '../../lib/admin-queries/invoice_billings_queries.php';
?>

<link rel="stylesheet" href="../../assets/css/admin-css/invoice_billings.css">
<link rel="stylesheet" href="../../assets/css/admin-css/header.css">

<div class="page-inner">

  <div class="dash-page-header">
    <div class="dash-header-left">
      <h1 class="dash-title">Invoices &amp; Billing</h1>
      <p class="dash-subtitle">Generate, send, and track invoices for all tenants.</p>
    </div>
    <div class="dash-header-actions">
      <button class="inv-new-btn" id="openNewInvoiceBtn">
        <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <line x1="12" y1="5" x2="12" y2="19" />
          <line x1="5" y1="12" x2="19" y2="12" />
        </svg>
        New Invoice
      </button>
    </div>
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

  <div class="inv-card" style="overflow:visible;">

    <div class="inv-card-top" style="overflow:visible;">
      <span class="inv-card-title">Invoice List</span>
      <div class="inv-filters">

        <!-- Month/Year Picker -->
        <div style="position:relative;" id="invMonthPickerWrap">
          <button type="button" id="invMonthPickerBtn" onclick="toggleInvMonthPicker()"
            style="display:flex;align-items:center;gap:7px;padding:7px 12px;border:1.5px solid var(--border);border-radius:var(--radius);background:#fff;font-size:13px;font-weight:600;cursor:pointer;color:var(--inv-text);white-space:nowrap;height:34px;box-sizing:border-box;">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="13" height="13">
              <rect x="3" y="4" width="18" height="18" rx="2" />
              <line x1="16" y1="2" x2="16" y2="6" />
              <line x1="8" y1="2" x2="8" y2="6" />
              <line x1="3" y1="10" x2="21" y2="10" />
            </svg>
            <?php
            [$dy, $dm] = explode('-', $inv_default_month_val);
            $month_names = [
              'January',
              'February',
              'March',
              'April',
              'May',
              'June',
              'July',
              'August',
              'September',
              'October',
              'November',
              'December'
            ];
            $inv_default_label = $month_names[(int) $dm - 1] . ' ' . $dy;
            ?>
            <span id="invMonthPickerLabel"><?= htmlspecialchars($inv_default_label) ?></span>
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="11" height="11">
              <polyline points="6 9 12 15 18 9" />
            </svg>
          </button>
          <input type="hidden" id="invMonthFilter" value="<?= htmlspecialchars($inv_default_month_val) ?>">

          <!-- Dropdown Calendar -->
          <div id="invMonthPickerDropdown"
            style="display:none;position:absolute;top:calc(100% + 6px);right:0;z-index:999;background:#fff;border:1.5px solid var(--inv-border);border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,0.13);padding:16px;min-width:252px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
              <button type="button" onclick="changeInvPickerYear(-1)"
                style="display:none;border:none;background:none;cursor:pointer;padding:4px 8px;border-radius:6px;font-size:17px;color:var(--inv-text-soft);line-height:1;">‹</button>
              <span id="invPickerYear"
                style="font-size:13.5px;font-weight:700;color:var(--inv-text);"><?= $inv_cur_picker_year ?></span>
              <button type="button" onclick="changeInvPickerYear(1)"
                style="display:none;border:none;background:none;cursor:pointer;padding:4px 8px;border-radius:6px;font-size:17px;color:var(--inv-text-soft);line-height:1;">›</button>
            </div>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:5px;">
              <?php
              $inv_months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
              foreach ($inv_months as $i => $mon):
                ?>
                <button type="button" data-month="<?= str_pad($i + 1, 2, '0', STR_PAD_LEFT) ?>"
                  onclick="selectInvPickerMonth(this)" class="inv-picker-month-btn"
                  style="padding:6px 4px;border:1.5px solid var(--inv-border);border-radius:7px;font-size:11.5px;font-weight:500;cursor:pointer;background:var(--inv-bg-soft);color:var(--inv-text);transition:all .15s;">
                  <?= $mon ?>
                </button>
              <?php endforeach; ?>
            </div>
            <div style="margin-top:10px;display:flex;justify-content:flex-end;gap:6px;">
              <div style="display:flex;gap:6px;">
                <button type="button" onclick="closeInvMonthPicker()"
                  style="padding:5px 11px;border:1.5px solid var(--inv-border);border-radius:6px;font-size:12px;background:none;cursor:pointer;color:var(--inv-text-soft);">Cancel</button>
                <button type="button" onclick="applyInvMonthPicker()"
                  style="padding:5px 13px;border:none;border-radius:6px;font-size:12px;font-weight:600;background:var(--primary,#3b6ef5);color:white;cursor:pointer;">Apply</button>
              </div>
            </div>
          </div>
        </div>

        <div class="inv-search">
          <input type="text" id="searchFilter" placeholder="Search tenant or invoice" autocomplete="off">
        </div>
        <!-- Custom Status Dropdown -->
        <div class="inv-status-dropdown-wrap" id="statusDropdownWrap">
          <button type="button" class="inv-status-trigger" id="statusTrigger" onclick="toggleStatusDropdown()">
            <span id="statusTriggerLabel">All Status</span>
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="12" height="12"
              id="statusChevron">
              <polyline points="6 9 12 15 18 9" />
            </svg>
          </button>
          <!-- Hidden input so JS filter still reads #statusFilter -->
          <input type="hidden" id="statusFilter" value="">
          <div class="inv-status-menu" id="statusMenu" style="display:none;">
            <button type="button" class="inv-status-opt active" data-value="" onclick="selectStatusOpt(this)">All
              Status</button>
            <button type="button" class="inv-status-opt" data-value="Paid" onclick="selectStatusOpt(this)">
              <span class="inv-status-dot paid"></span>Paid
            </button>
            <button type="button" class="inv-status-opt" data-value="Pending" onclick="selectStatusOpt(this)">
              <span class="inv-status-dot pending"></span>Pending
            </button>
            <button type="button" class="inv-status-opt" data-value="Overdue" onclick="selectStatusOpt(this)">
              <span class="inv-status-dot overdue"></span>Overdue
            </button>
            <button type="button" class="inv-status-opt" data-value="Sent" onclick="selectStatusOpt(this)">
              <span class="inv-status-dot sent"></span>Sent
            </button>
          </div>
        </div>
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
          <?php if (empty($invoices)): ?>
            <tr id="noDataRow">
              <td colspan="9" style="text-align:center;padding:48px;color:#aab;">
                No invoices yet. Create your first one!
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($invoices as $inv):
              $unit = !empty($inv['unit']) ? $inv['unit'] : '—';
              $bc = badge_class($inv['status']);
              ?>
              <tr data-id="<?= (int) $inv['id'] ?>" data-status="<?= htmlspecialchars($inv['status']) ?>"
                data-month="<?= htmlspecialchars($inv['month_val']) ?>"
                data-search="<?= strtolower(htmlspecialchars($inv['invoice_no'] . ' ' . $inv['tenant_name'] . ' ' . $unit)) ?>"
                data-inv='<?= htmlspecialchars(json_encode([
                  'id' => $inv['id'],
                  'invoice_no' => $inv['invoice_no'],
                  'tenant' => $inv['tenant_name'],
                  'email' => $inv['tenant_email'],
                  'photo' => $inv['tenant_photo'] ?? '',
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
                    <button class="inv-btn ghost more-btn" data-id="<?= (int) $inv['id'] ?>" title="More">⋯</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
        </tbody>
      </table>
    </div>

    <div class="inv-empty" id="emptyState">
      <svg width="38" height="38" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <circle cx="11" cy="11" r="8" />
        <line x1="21" y1="21" x2="16.65" y2="16.65" />
      </svg>
      <p>No invoices match your filters.</p>
    </div>

    <!-- Pagination outside table-wrap — never scrolls horizontally -->
    <div id="invTableFoot" class="pay-pagination-wrap" style="display:none;">
      <div class="inv-pagination">
        <span class="inv-page-info" id="invPageInfo"></span>
        <div class="inv-page-controls" id="invPageControls" style="display:none;">
          <button type="button" id="invPrevBtn" class="inv-chevron-btn" onclick="invChangePage(-1)" disabled>
            <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" width="14" height="14">
              <polyline points="15 18 9 12 15 6" />
            </svg>
          </button>
          <span id="invPageNumbers" class="inv-page-numbers"></span>
          <button type="button" id="invNextBtn" class="inv-chevron-btn" onclick="invChangePage(1)" disabled>
            <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" width="14" height="14">
              <polyline points="9 18 15 12 9 6" />
            </svg>
          </button>
        </div>
      </div>
    </div>

  </div>

  <!-- ── Modals (unchanged) ─────────────────────────────────────────────── -->

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
    <div class="inv-modal inv-modal--view">
      <div class="inv-modal-head inv-modal-head--branded">
        <div class="inv-modal-head-inner">
          <div class="inv-modal-head-eyebrow">Invoice</div>
          <div class="inv-modal-head-no" id="viewInvTitle">—</div>
          <span class="inv-badge inv-badge--header" id="vi_badge_header">—</span>
        </div>
        <button class="inv-modal-close inv-modal-close--light" id="closeViewInvoice" aria-label="Close">✕</button>
      </div>

      <div class="inv-modal-body inv-modal-body--view">

        <!-- Tenant / contact strip -->
        <div class="inv-view-tenant-strip">
          <div class="inv-view-avatar" id="vi_avatar_initials">—</div>
          <div>
            <div class="inv-view-tenant-name" id="vi_tenant">—</div>
            <div class="inv-view-tenant-email" id="vi_email">—</div>
          </div>
        </div>

        <!-- Detail grid -->
        <div class="inv-detail-grid inv-detail-grid--4">
          <div class="inv-detail-item">
            <div class="inv-detail-label">Unit</div>
            <div class="inv-detail-value" id="vi_unit">—</div>
          </div>
          <div class="inv-detail-item">
            <div class="inv-detail-label">Issued</div>
            <div class="inv-detail-value" id="vi_issued">—</div>
          </div>
          <div class="inv-detail-item">
            <div class="inv-detail-label">Due Date</div>
            <div class="inv-detail-value inv-detail-value--due" id="vi_due">—</div>
          </div>
          <div class="inv-detail-item">
            <div class="inv-detail-label">Total Amount</div>
            <div class="inv-detail-value inv-detail-value--total" id="vi_total">—</div>
          </div>
        </div>

        <!-- Items box -->
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
    <div class="inv-modal inv-modal--send">
      <div class="inv-modal-head">
        <span class="inv-modal-title" id="sendModalTitle">
          <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"
            style="margin-right:6px;vertical-align:-2px;">
            <line x1="22" y1="2" x2="11" y2="13" />
            <polygon points="22 2 15 22 11 13 2 9 22 2" />
          </svg>
          Send Invoice
        </span>
        <button class="inv-modal-close" id="closeSendModal" aria-label="Close">✕</button>
      </div>

      <div class="inv-modal-body">

        <div id="sendModalLoading" class="inv-spinner-wrap">
          <div class="inv-spinner"></div> Loading invoice…
        </div>

        <div id="sendModalContent" style="display:none;">

          <!-- Info banner -->
          <div class="send-info-banner">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <circle cx="12" cy="12" r="10" />
              <line x1="12" y1="8" x2="12" y2="12" />
              <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
            <span>A PayMongo payment link will be generated and included in this email.</span>
          </div>

          <!-- Preview card -->
          <div class="send-preview-card">
            <div class="send-preview-row">
              <span class="send-preview-label">Invoice #</span>
              <span class="send-preview-val send-preview-val--no" id="si_invoice_no">—</span>
            </div>
            <div class="send-preview-row">
              <span class="send-preview-label">Tenant</span>
              <span class="send-preview-val" id="si_tenant">—</span>
            </div>
            <div class="send-preview-row">
              <span class="send-preview-label">Email</span>
              <span class="send-preview-val send-preview-val--email" id="si_email">—</span>
            </div>
            <div class="send-preview-row">
              <span class="send-preview-label">Unit</span>
              <span class="send-preview-val" id="si_unit">—</span>
            </div>
            <div class="send-preview-row">
              <span class="send-preview-label">Items</span>
              <span class="send-preview-val" id="si_items">—</span>
            </div>
            <div class="send-preview-row send-preview-row--total">
              <span class="send-preview-label">Total</span>
              <span class="send-preview-val send-preview-val--total" id="si_total">—</span>
            </div>
            <div class="send-preview-row">
              <span class="send-preview-label">Due Date</span>
              <span class="send-preview-val send-preview-val--due" id="si_due">—</span>
            </div>
          </div>

          <div id="sendModalError" class="send-error"></div>
        </div>

      </div>

      <div class="inv-modal-foot">
        <button type="button" class="inv-btn secondary" id="cancelSendModal">Cancel</button>
        <button type="button" class="inv-btn primary inv-btn--send" id="confirmSendBtn" style="display:none;">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <line x1="22" y1="2" x2="11" y2="13" />
            <polygon points="22 2 15 22 11 13 2 9 22 2" />
          </svg>
          Send Invoice
        </button>
      </div>
    </div>
  </div>

  <div class="inv-overlay" id="deleteInvoiceModal">
    <div class="inv-modal" style="max-width:400px;text-align:center;">
      <div style="padding:28px 24px 8px;">
        <div
          style="width:56px;height:56px;margin:0 auto 14px;border-radius:50%;background:#fee2e2;display:flex;align-items:center;justify-content:center;">
          <svg fill="none" stroke="#dc2626" stroke-width="2" viewBox="0 0 24 24" width="26" height="26">
            <polyline points="3 6 5 6 21 6" />
            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
            <path d="M10 11v6M14 11v6" />
            <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
          </svg>
        </div>
        <div class="inv-modal-title" style="margin-bottom:10px;">Delete Invoice?</div>
        <p style="color:#64748b;font-size:13.5px;margin:0 0 6px;font-weight:600;"><span id="deleteInvoiceRef"></span>
          will be permanently removed.</p>
        <p style="color:#94a3b8;font-size:12px;margin:0 0 24px;">This cannot be undone. The invoice record will not be
          affected.</p>
      </div>
      <div class="inv-modal-foot" style="justify-content:center;gap:12px;padding:0px 24px 24px;border-top:none;">
        <button type="button" class="inv-btn secondary" id="cancelDeleteInvoice"
          style="padding:11px 20px;font-size:14px;font-weight:600;border-radius:10px;">Cancel</button>
        <button type="button" class="inv-btn" id="confirmDeleteInvoice"
          style="background:#dc2626;color:#fff;border-color:#dc2626;box-shadow:0 2px 8px rgba(220,38,38,.25);padding:11px 20px;font-size:14px;font-weight:600;border-radius:10px;">
          Delete
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

  <!-- Month picker — needs PHP-baked values -->
  <script>
    (function () {
      let invPickerYear = <?= (int) explode('-', $inv_default_month_val)[0] ?>;
      let invSelectedMonth = '<?= explode('-', $inv_default_month_val)[1] ?>';

      // Single source of truth — loops ALL buttons so only one can ever be active
      function _highlightActive() {
        document.querySelectorAll('.inv-picker-month-btn').forEach(b => {
          const isActive = invSelectedMonth !== null && b.dataset.month === invSelectedMonth;
          b.style.background = isActive ? 'var(--primary,#3b6ef5)' : 'var(--white,#fff)';
          b.style.borderColor = isActive ? 'var(--primary,#3b6ef5)' : 'var(--border,#e5e7eb)';
          b.style.color = isActive ? 'white' : 'var(--text,#1e2533)';
          b.style.fontWeight = isActive ? '700' : '500';
        });
      }

      window._invSyncPickerState = function (monthVal) {
        if (!monthVal) return;
        const [yr, mn] = monthVal.split('-');
        invPickerYear = parseInt(yr);
        invSelectedMonth = mn;
        document.getElementById('invPickerYear').textContent = invPickerYear;
        _highlightActive();
      };

      window.toggleInvMonthPicker = function () {
        const d = document.getElementById('invMonthPickerDropdown');
        const isOpen = d.style.display !== 'none';
        d.style.display = isOpen ? 'none' : 'block';
        if (!isOpen) _highlightActive(); // sync on every open
      };
      window.closeInvMonthPicker = function () {
        document.getElementById('invMonthPickerDropdown').style.display = 'none';
      };
      window.changeInvPickerYear = function (dir) {
        const newYear = invPickerYear + dir;
        if (newYear < 2000 || newYear > new Date().getFullYear() + 1) return;
        invPickerYear = newYear;
        document.getElementById('invPickerYear').textContent = invPickerYear;
      };
      window.selectInvPickerMonth = function (btn) {
        invSelectedMonth = btn.dataset.month;
        _highlightActive(); // update all buttons — dual-highlight impossible
      };

      window.applyInvMonthPicker = function () {
        if (!invSelectedMonth) return; // just do nothing if nothing selected
        const val = invPickerYear + '-' + invSelectedMonth;
        document.getElementById('invMonthFilter').value = val;
        const names = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        document.getElementById('invMonthPickerLabel').textContent = names[parseInt(invSelectedMonth) - 1] + ' ' + invPickerYear;
        closeInvMonthPicker();
        document.getElementById('invMonthFilter').dispatchEvent(new Event('change'));
      };
      document.addEventListener('click', function (e) {
        const wrap = document.getElementById('invMonthPickerWrap');
        if (wrap && !wrap.contains(e.target)) closeInvMonthPicker();
      });

      // Initialize: highlight current month and trigger filter on page load
      _highlightActive();
      document.getElementById('invMonthFilter').dispatchEvent(new Event('change'));
    })();
  </script>

  <script>
    window.PS_CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
  </script>
  <script src="../../assets/js/admin/invoice_billings.js"></script>

</div><!-- /page-inner -->
<?php include '../../includes/layout_close.php'; ?>