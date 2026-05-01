<?php
include '../../includes/session.php';
if ($_SESSION['role'] !== 'user') {
    echo '<!DOCTYPE html>
<html>
<head>

</head>
<body>

<script src="../../assets/js/user-js/payment-inline.js"></script>
</body>
</html>';
    exit;
}

$first_name = htmlspecialchars($_SESSION['first_name'] ?? $_SESSION['name'] ?? 'Guest');
$last_name = htmlspecialchars($_SESSION['last_name'] ?? '');
$full_name = trim($first_name . ' ' . $last_name);
$email = htmlspecialchars($_SESSION['email'] ?? '');
$initials = strtoupper(mb_substr($first_name, 0, 1) . mb_substr($last_name, 0, 1));
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

$page_title = 'Payment Methods';
$page_hero_html = 'Payment <em>Methods</em>';
$page_hero_sub = 'Manage your cards, e-wallets, and billing details securely.';
$page_hero_icon = '<rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>';
$active_nav = 'payment';
require '../../includes/_layout.php';

require_once '../../includes/db.php';
$userId = (int) $_SESSION['user_id'];

// DB: Cards
$cardColors = ['Visa' => 'linear-gradient(135deg,#1a3d7c,#2563c4)', 'Mastercard' => 'linear-gradient(135deg,#b5310c,#f47321)', 'Amex' => 'linear-gradient(135deg,#007bc0,#00205b)'];
$cRes = mysqli_query($conn, "SELECT * FROM payment_methods WHERE user_id=$userId AND type='card' AND is_active=1 ORDER BY is_default DESC");
$cards = [];
while ($r = mysqli_fetch_assoc($cRes)) {
    $r['color'] = $cardColors[$r['provider']] ?? 'linear-gradient(135deg,#1a3d7c,#2563c4)';
    $r['holder'] = $r['holder_name'] ?: $full_name;
    $r['expiry'] = $r['expiry_month'] && $r['expiry_year'] ? str_pad($r['expiry_month'], 2, '0', STR_PAD_LEFT) . '/' . $r['expiry_year'] : '—';
    $cards[] = $r;
}

// DB: E-wallets
$ewallet_icons = ['GCash' => '../../assets/images/gcash-icon.png', 'Maya' => '../../assets/images/maya-icon.png', 'PayPal' => '../../assets/images/paypal-icon.png', 'ShopeePay' => '../../assets/images/shopeepay-icon.png'];
$ewRes = mysqli_query($conn, "SELECT * FROM payment_methods WHERE user_id=$userId AND type='ewallet' AND is_active=1 ORDER BY created_at");
$linked_map = [];
while ($r = mysqli_fetch_assoc($ewRes)) {
    $linked_map[$r['provider']] = ['name' => $r['provider'], 'icon' => $ewallet_icons[$r['provider']] ?? '', 'linked' => true, 'number' => $r['account_number'], 'id' => $r['id']];
}
$ewallets = [];
foreach (['GCash', 'Maya', 'PayPal', 'ShopeePay'] as $name) {
    $ewallets[] = $linked_map[$name] ?? ['name' => $name, 'icon' => $ewallet_icons[$name] ?? '', 'linked' => false, 'number' => null, 'id' => null];
}

// DB: Billing history
$bRes = mysqli_query($conn, "
    SELECT py.payment_id, py.payment_date, py.amount_paid, py.payment_method, py.payment_status,
           CONCAT(COALESCE(u.unit_name, u.unit_number,'—'), ' · ', DATEDIFF(b.checkout_date,b.checkin_date), ' nights') AS desc_text,
           p.property_name
    FROM payments py
    JOIN bookings b ON b.booking_id=py.booking_id
    JOIN units u ON u.unit_id=b.unit_id
    LEFT JOIN properties p ON p.property_id=u.property_id
    WHERE b.user_id=$userId ORDER BY py.payment_date DESC LIMIT 20");
$bills = [];
while ($r = mysqli_fetch_assoc($bRes)) {
    $bills[] = ['date' => date('M j, Y', strtotime($r['payment_date'])), 'desc' => $r['property_name'] . ' - ' . $r['desc_text'], 'amount' => 'P' . number_format($r['amount_paid'], 2), 'status' => $r['payment_status'], 'method' => $r['payment_method']];
}
?>

<link rel="stylesheet" href="../../assets/css/user-css/payment.css" />

<div class="page-two-col">
    <div class="col-main">

        <div class="card reveal">
            <div class="card-title">
                <svg viewBox="0 0 24 24">
                    <rect x="1" y="4" width="22" height="16" rx="2" />
                    <line x1="1" y1="10" x2="23" y2="10" />
                </svg>
                Saved Cards
                <button class="btn-primary" style="margin-left:auto;font-size:0.74rem;padding:8px 18px;"
                    onclick="openAddCard()">+ Add New Card</button>
            </div>
            <div class="cards-list">
                <?php if (empty($cards)): ?>
                    <div style="padding:20px;text-align:center;color:var(--text-soft);">No saved cards yet.</div>
                    <?php else:
                    foreach ($cards as $c): ?>
                        <div class="card-item-wrap" id="card-<?php echo $c['id']; ?>">
                            <div class="card-visual" style="background:<?php echo $c['color']; ?>">
                                <?php if ($c['is_default']): ?>
                                    <div class="cv-default-badge">Default</div><?php endif; ?>
                                <div>
                                    <div class="cv-chip"></div>
                                    <div class="cv-number">•••• •••• •••• <?php echo htmlspecialchars($c['last4']); ?></div>
                                </div>
                                <div class="cv-footer">
                                    <div>
                                        <div class="cv-label">Card Holder</div>
                                        <div class="cv-value"><?php echo strtoupper(htmlspecialchars($c['holder'])); ?></div>
                                    </div>
                                    <div style="text-align:right;">
                                        <div class="cv-label">Expires</div>
                                        <div class="cv-value"><?php echo htmlspecialchars($c['expiry']); ?></div>
                                    </div>
                                    <div class="cv-type"><?php echo htmlspecialchars($c['provider']); ?></div>
                                </div>
                            </div>
                            <div class="card-actions">
                                <?php if (!$c['is_default']): ?>
                                    <button class="btn-secondary" style="font-size:0.72rem;padding:7px 14px;"
                                        onclick="setDefault(<?php echo $c['id']; ?>,'card',this)">Set Default</button>
                                <?php endif; ?>
                                <button class="btn-danger" style="font-size:0.72rem;padding:7px 14px;"
                                    onclick="removePaymentMethod(<?php echo $c['id']; ?>,'card',this)">Remove</button>
                            </div>
                        </div>
                <?php endforeach;
                endif; ?>
            </div>
        </div>

        <div class="card reveal rd1">
            <div class="card-title">
                <svg viewBox="0 0 24 24">
                    <path d="M21 12V7H5a2 2 0 010-4h14v4" />
                    <path d="M3 5v14a2 2 0 002 2h16v-5" />
                    <path d="M18 12a2 2 0 000 4h4v-4z" />
                </svg>
                E-Wallets
            </div>
            <div class="ewallet-grid">
                <?php foreach ($ewallets as $w): ?>
                    <div class="ewallet-item <?php echo $w['linked'] ? 'linked' : ''; ?>">
                        <div class="ewallet-icon"><img src="<?php echo $w['icon']; ?>" alt="<?php echo $w['name']; ?>"></div>
                        <div class="ewallet-info">
                            <div class="ewallet-name"><?php echo $w['name']; ?></div>
                            <div class="ewallet-num"><?php echo $w['linked'] ? $w['number'] : 'Not linked'; ?></div>
                        </div>
                        <?php if ($w['linked']): ?>
                            <span class="badge badge-green">Linked</span>
                        <?php else: ?>
                            <button class="btn-secondary" style="font-size:0.7rem;padding:6px 12px;white-space:nowrap;"
                                type="button">Link</button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card reveal rd2">
            <div class="card-title">
                <svg viewBox="0 0 24 24">
                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                    <polyline points="14 2 14 8 20 8" />
                    <line x1="16" y1="13" x2="8" y2="13" />
                    <line x1="16" y1="17" x2="8" y2="17" />
                    <polyline points="10 9 9 9 8 9" />
                </svg>
                Billing History
            </div>
            <div style="overflow-x:auto;">
                <table class="billing-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Method</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bills as $b): ?>
                            <tr>
                                <td><?php echo $b['date']; ?></td>
                                <td><?php echo $b['desc']; ?></td>
                                <td style="font-size:0.78rem;color:var(--text-soft);"><?php echo $b['method']; ?></td>
                                <td class="bt-amount"><?php echo $b['amount']; ?></td>
                                <td><span
                                        class="badge <?php echo $b['status'] === 'paid' ? 'badge-green' : ($b['status'] === 'pending' ? 'badge-gold' : 'badge-red'); ?>"><?php echo ucfirst($b['status']); ?></span>
                                </td>
                                <td><button class="btn-secondary" style="font-size:0.7rem;padding:5px 12px;"
                                        onclick="showToast('Invoice downloaded.')">Invoice</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /col-main -->

    <!-- ── Payment Sidebar ── -->
    <div class="col-side">

        <div class="widget-card reveal rd1">
            <div class="widget-title">
                <svg viewBox="0 0 24 24">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                </svg>
                Payment Security
            </div>
            <div class="activity-item">
                <div class="activity-dot green"></div>
                <div class="activity-desc"><strong>256-bit SSL</strong> encryption on all transactions</div>
            </div>
            <div class="activity-item">
                <div class="activity-dot green"></div>
                <div class="activity-desc"><strong>PCI-DSS</strong> compliant card storage</div>
            </div>
            <div class="activity-item">
                <div class="activity-dot green"></div>
                <div class="activity-desc">Card numbers are <strong>never stored</strong> in full</div>
            </div>
            <div class="activity-item">
                <div class="activity-dot gold"></div>
                <div class="activity-desc"><strong>3D Secure</strong> verification for all card payments</div>
            </div>
        </div>

        <div class="tip-card reveal rd2">
            <div class="tip-card-label">💳 Payment tip</div>
            <div class="tip-card-title">Save a default method</div>
            <div class="tip-card-body">Set a default card or e-wallet to speed up checkout when making new bookings.</div>
        </div>

        <div class="widget-card reveal rd3">
            <div class="widget-title">
                <svg viewBox="0 0 24 24">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                </svg>
                Accepted Methods
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Visa / Mastercard</span>
                <span class="mini-stat-val" style="color:#16a34a;">✓</span>
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">GCash</span>
                <span class="mini-stat-val" style="color:#16a34a;">✓</span>
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Maya</span>
                <span class="mini-stat-val" style="color:#16a34a;">✓</span>
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">PayPal</span>
                <span class="mini-stat-val" style="color:#16a34a;">✓</span>
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Cash (on-site)</span>
                <span class="mini-stat-val" style="color:#16a34a;">✓</span>
            </div>
        </div>

    </div><!-- /col-side -->
</div><!-- /page-two-col -->

<div class="modal-overlay" id="addCardModal">
    <div class="modal-box" style="max-width:420px;">
        <button class="modal-close-btn" onclick="closeAddCard()">✕</button>
        <div class="modal-title">Add New Card</div>
        <div class="modal-sub">Your details are encrypted and stored securely.</div>

        <!-- Live card preview -->
        <div id="cardPreview" style="
            width:100%; aspect-ratio:1.586; border-radius:14px; margin-bottom:20px;
            background:linear-gradient(135deg,var(--blue-800),var(--blue-500));
            padding:18px 20px; display:flex; flex-direction:column;
            justify-content:space-between; position:relative; overflow:hidden;
            box-shadow:0 10px 30px rgba(10,22,40,.3);">
            <!-- bg circles -->
            <div
                style="position:absolute;top:-30px;right:-30px;width:140px;height:140px;border-radius:50%;background:rgba(255,255,255,0.06);">
            </div>
            <div
                style="position:absolute;bottom:-40px;left:10px;width:160px;height:160px;border-radius:50%;background:rgba(255,255,255,0.04);">
            </div>
            <!-- chip -->
            <div
                style="width:34px;height:26px;background:rgba(232,200,122,0.75);border-radius:4px;position:relative;z-index:1;">
                <div style="position:absolute;top:50%;left:0;right:0;height:1px;background:rgba(0,0,0,0.2);"></div>
            </div>
            <!-- number -->
            <div id="previewNumber"
                style="font-family:'Playfair Display',serif;font-size:1.1rem;letter-spacing:0.2em;color:rgba(255,255,255,0.9);position:relative;z-index:1;text-align:center;">
                •••• •••• •••• ••••
            </div>
            <!-- footer -->
            <div style="display:flex;justify-content:space-between;align-items:flex-end;position:relative;z-index:1;">
                <div>
                    <div
                        style="font-size:0.55rem;letter-spacing:0.1em;text-transform:uppercase;color:rgba(255,255,255,0.5);margin-bottom:3px;">
                        Card Holder</div>
                    <div id="previewHolder"
                        style="font-size:0.78rem;font-weight:600;color:rgba(255,255,255,0.9);letter-spacing:0.04em;">
                        YOUR NAME</div>
                </div>
                <div style="text-align:right;">
                    <div
                        style="font-size:0.55rem;letter-spacing:0.1em;text-transform:uppercase;color:rgba(255,255,255,0.5);margin-bottom:3px;">
                        Expires</div>
                    <div id="previewExpiry" style="font-size:0.78rem;font-weight:600;color:rgba(255,255,255,0.9);">MM/YY
                    </div>
                </div>
                <div id="previewType"
                    style="font-family:'Playfair Display',serif;font-size:1rem;font-weight:700;color:rgba(255,255,255,0.6);">
                    CARD</div>
            </div>
        </div>

        <div class="form-field" style="margin-bottom:14px;">
            <label>Card Number</label>
            <input type="text" id="cardNumber" maxlength="19" placeholder="0000 0000 0000 0000"
                style="font-family:'Playfair Display',serif;letter-spacing:0.12em;font-size:1rem;"
                oninput="formatCardNumber(this);updatePreview()">
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
            <div class="form-field">
                <label>Expiry Date</label>
                <input type="text" id="cardExpiry" maxlength="7" placeholder="MM / YY"
                    oninput="formatExpiry(this);updatePreview()">
            </div>
            <div class="form-field">
                <label>CVV</label>
                <input type="password" id="cardCvv" maxlength="4" placeholder="•••">
            </div>
        </div>

        <div class="form-field" style="margin-bottom:20px;">
            <label>Cardholder Name</label>
            <input type="text" id="cardHolder" placeholder="Name as printed on card" value="<?php echo $full_name; ?>"
                oninput="updatePreview()">
        </div>

        <div id="cardError"
            style="display:none;color:#ef4444;font-size:0.78rem;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:9px 12px;margin-bottom:14px;">
        </div>

        <div style="display:flex;gap:10px;">
            <button class="btn-secondary" style="flex:1;" onclick="closeAddCard()">Cancel</button>
            <button class="btn-primary" id="saveCardBtn" style="flex:2;" onclick="saveCard()">
                <svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;">
                    <rect x="1" y="4" width="22" height="16" rx="2" />
                    <line x1="1" y1="10" x2="23" y2="10" />
                </svg>
                Save Card
            </button>
        </div>
    </div>
</div>

<script src="../../assets/js/user-js/payment.js"></script>
<script>window.PS_RT_PAGE = 'payment';</script>
<?php require '../../includes/_layout_end.php'; ?>