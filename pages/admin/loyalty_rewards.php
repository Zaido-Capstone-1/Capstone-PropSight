<?php
include '../../includes/session.php';

if ($_SESSION['role'] !== 'admin') {
    echo '<!DOCTYPE html><html><body></body></html>';
    exit;
}

$page_title = 'Loyalty Rewards';
$active_page = 'loyalty_rewards';
include '../../includes/db.php';
include '../../includes/layout_open.php';

// Fetch all rewards
$rewards = [];
$stmt = $conn->prepare("SELECT * FROM loyalty_rewards ORDER BY points_cost ASC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $rewards[] = $row;
}
$stmt->close();

$total = count($rewards);
$active = count(array_filter($rewards, fn($r) => (int) $r['is_active'] === 1));
$inactive = $total - $active;
$avg_pts = $total ? (int) round(array_sum(array_column($rewards, 'points_cost')) / $total) : 0;
?>

<link rel="stylesheet" href="../../assets/css/admin-css/header.css">
<link rel="stylesheet" href="../../assets/css/admin-css/loyalty_rewards.css">

<div class="page-inner">
    <div class="dash-page-header">
        <div class="dash-header-left">
            <h1 class="dash-title">Loyalty Rewards</h1>
            <p class="dash-subtitle">Manage the rewards catalogue guests can redeem with their points.</p>
        </div>
        <div class="dash-header-actions">
            <button class="btn btn-primary" onclick="openAddModal()">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="15" height="15">
                    <line x1="12" y1="5" x2="12" y2="19" />
                    <line x1="5" y1="12" x2="19" y2="12" />
                </svg>
                Add Reward
            </button>
        </div>
    </div>

    <!-- Stats -->
    <div class="rw-stats">
        <div class="rw-stat">
            <div class="rw-stat-left">
                <div class="rw-stat-label">Total Rewards</div>
                <div class="rw-stat-value"><?php echo $total; ?></div>
            </div>
            <div class="rw-stat-icon si-blue">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path d="M20 12V22H4V12" />
                    <path d="M22 7H2v5h20V7z" stroke-linejoin="round" />
                    <path d="M12 22V7" />
                    <path d="M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7z" />
                    <path d="M12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z" />
                </svg>
            </div>
        </div>
        <div class="rw-stat">
            <div class="rw-stat-left">
                <div class="rw-stat-label">Active</div>
                <div class="rw-stat-value"><?php echo $active; ?></div>
            </div>
            <div class="rw-stat-icon si-green">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <polyline points="20 6 9 17 4 12" />
                </svg>
            </div>
        </div>
        <div class="rw-stat">
            <div class="rw-stat-left">
                <div class="rw-stat-label">Inactive</div>
                <div class="rw-stat-value"><?php echo $inactive; ?></div>
            </div>
            <div class="rw-stat-icon si-red">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="9" />
                    <line x1="15" y1="9" x2="9" y2="15" />
                    <line x1="9" y1="9" x2="15" y2="15" />
                </svg>
            </div>
        </div>
        <div class="rw-stat">
            <div class="rw-stat-left">
                <div class="rw-stat-label">Median Points</div>
                <div class="rw-stat-value"><?php echo $total ? number_format($avg_pts) . ' pts' : '—'; ?></div>
            </div>
            <div class="rw-stat-icon si-gold">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <circle cx="12" cy="8" r="5" />
                    <path d="M14.5 11.9L16 21l-4-2.4-4 2.4 1.5-9.1" stroke-linejoin="round" />
                </svg>
            </div>
        </div>
    </div>

    <!-- Table Card -->
    <div class="rw-card">
        <div class="rw-card-header">
            <div class="rw-card-title">All Rewards</div>
            <div class="rw-controls">
                <div class="rw-search">
                    <svg viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="7" />
                        <line x1="16.5" y1="16.5" x2="22" y2="22" />
                    </svg>
                    <input type="text" id="rwSearchInput" placeholder="Search rewards…" oninput="filterTable()">
                </div>

                <!-- Custom Status Dropdown (matches expenses/payments style) -->
                <div class="rw-status-dropdown-wrap" id="rwStatusDropdownWrap">
                    <button type="button" class="rw-status-trigger" id="rwStatusTrigger"
                        onclick="toggleRwStatusDropdown()">
                        <span id="rwStatusTriggerLabel">All Status</span>
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="12"
                            height="12" id="rwStatusChevron">
                            <polyline points="6 9 12 15 18 9" />
                        </svg>
                    </button>
                    <!-- Hidden input read by filterTable() -->
                    <input type="hidden" id="rwStatusFilter" value="">
                    <div class="rw-status-menu" id="rwStatusMenu" style="display:none;">
                        <button type="button" class="rw-status-opt active" data-value=""
                            onclick="selectRwStatusOpt(this)">All Status</button>
                        <button type="button" class="rw-status-opt" data-value="1" onclick="selectRwStatusOpt(this)">
                            <span class="rw-status-dot rw-dot-active"></span>Active
                        </button>
                        <button type="button" class="rw-status-opt" data-value="0" onclick="selectRwStatusOpt(this)">
                            <span class="rw-status-dot rw-dot-inactive"></span>Inactive
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="rw-table-wrap">
            <table class="rw-table" id="rwTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Reward Name</th>
                        <th>Description</th>
                        <th>Points Cost</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rewards)): ?>
                        <tr>
                            <td colspan="6">
                                <div class="rw-empty">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path d="M20 12V22H4V12" />
                                        <path d="M22 7H2v5h20V7z" stroke-linejoin="round" />
                                        <path d="M12 22V7" />
                                    </svg>
                                    <p style="font-size:14px;font-weight:600;color:#64748b;">No rewards yet</p>
                                    <p style="font-size:13px;">Click "Add Reward" to create the first one.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rewards as $i => $r): ?>
                            <tr data-id="<?php echo $r['reward_id']; ?>"
                                data-name="<?php echo htmlspecialchars($r['name'], ENT_QUOTES); ?>"
                                data-desc="<?php echo htmlspecialchars($r['description'] ?? '', ENT_QUOTES); ?>"
                                data-pts="<?php echo (int) $r['points_cost']; ?>"
                                data-active="<?php echo (int) $r['is_active']; ?>">
                                <td style="color:#94a3b8;font-size:12px;"><?php echo $i + 1; ?></td>
                                <td class="rw-reward-name"><?php echo htmlspecialchars($r['name']); ?></td>
                                <td class="rw-reward-desc"><?php echo htmlspecialchars($r['description'] ?? '—'); ?></td>
                                <td>
                                    <span class="pts-chip">
                                        <svg viewBox="0 0 24 24" fill="none" width="11" height="11" stroke="currentColor"
                                            stroke-width="2">
                                            <circle cx="12" cy="8" r="5" />
                                            <path d="M14.5 11.9L16 21l-4-2.4-4 2.4 1.5-9.1" />
                                        </svg>
                                        <?php echo number_format($r['points_cost']); ?> pts
                                    </span>
                                </td>
                                <td>
                                    <?php if ((int) $r['is_active']): ?>
                                        <span class="badge-active">
                                            <svg viewBox="0 0 24 24" fill="none" width="9" height="9" stroke="currentColor"
                                                stroke-width="3">
                                                <polyline points="20 6 9 17 4 12" />
                                            </svg>
                                            Active
                                        </span>
                                    <?php else: ?>
                                        <span class="badge-inactive">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <button class="btn-icon" title="Edit"
                                            onclick="openEditModal(<?php echo $r['reward_id']; ?>)">
                                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                                                width="14" height="14">
                                                <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" />
                                                <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" />
                                            </svg>
                                        </button>
                                        <button class="btn-icon <?php echo (int) $r['is_active'] ? 'danger' : 'success'; ?>"
                                            title="<?php echo (int) $r['is_active'] ? 'Deactivate' : 'Activate'; ?>"
                                            onclick="toggleReward(<?php echo $r['reward_id']; ?>, <?php echo (int) $r['is_active'] ? 0 : 1; ?>)">
                                            <?php if ((int) $r['is_active']): ?>
                                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                                                    width="14" height="14">
                                                    <circle cx="12" cy="12" r="9" />
                                                    <line x1="9" y1="9" x2="15" y2="15" />
                                                    <line x1="15" y1="9" x2="9" y2="15" />
                                                </svg>
                                            <?php else: ?>
                                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                                                    width="14" height="14">
                                                    <circle cx="12" cy="12" r="9" />
                                                    <polyline points="9 12 11 14 15 10" />
                                                </svg>
                                            <?php endif; ?>
                                        </button>
                                        <button class="btn-icon danger" title="Delete"
                                            onclick="openDeleteModal(<?php echo $r['reward_id']; ?>, '<?php echo htmlspecialchars($r['name'], ENT_QUOTES); ?>')">
                                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                                                width="14" height="14">
                                                <polyline points="3 6 5 6 21 6" />
                                                <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6" />
                                                <path d="M10 11v6M14 11v6" />
                                                <path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                                            <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div id="rwNoResults" style="display:none;text-align:center;padding:48px 16px;">
            <svg width="36" height="36" fill="none" stroke="#cbd5e1" stroke-width="1.5" viewBox="0 0 24 24"
                style="margin:0 auto 10px;display:block;">
                <circle cx="11" cy="11" r="8" />
                <line x1="21" y1="21" x2="16.65" y2="16.65" />
            </svg>
            <p style="font-size:14px;font-weight:600;color:#64748b;margin:0 0 4px;">No matching rewards</p>
            <p style="font-size:13px;color:#94a3b8;margin:0;">Try adjusting your search or filter.</p>
        </div>
        <?php if (!empty($rewards)): ?>
            <div class="rw-card-footer" id="rwFooter">
                Showing <?php echo $total; ?> reward<?php echo $total !== 1 ? 's' : ''; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add / Edit Modal -->
<div class="rw-modal-overlay" id="rwModal">
    <div class="rw-modal">
        <button class="rw-close" onclick="closeModal()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
        </button>
        <div class="rw-modal-title" id="rwModalTitle">Add Reward</div>
        <input type="hidden" id="rwRewardId">
        <div class="rw-field">
            <label>Reward Name</label>
            <input type="text" id="rwName" placeholder="e.g. Free Night Stay" maxlength="100">
        </div>
        <div class="rw-field">
            <label>Description</label>
            <textarea id="rwDesc" placeholder="Short description shown to guests…" maxlength="255"></textarea>
        </div>
        <div class="rw-field">
            <label>Points Cost</label>
            <input type="number" id="rwPoints" placeholder="e.g. 500" min="1" max="99999">
        </div>
        <div class="rw-field">
            <label>Status</label>
            <select id="rwStatus">
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
        </div>
        <p id="rwErr" style="color:#dc2626;font-size:12.5px;margin:0;display:none;"></p>
        <div class="rw-modal-actions">
            <button class="btn-outline" onclick="closeModal()">Cancel</button>
            <button class="btn btn-primary" id="rwSaveBtn" onclick="saveReward()">Save Reward</button>
        </div>
    </div>
</div>

<!-- Toggle Reward Confirm Modal -->
<div class="rw-modal-overlay" id="rwToggleModal">
    <div class="rw-modal" style="max-width:400px;text-align:center;">
        <div id="rwToggleIcon"
            style="width:56px;height:56px;margin:0 auto 14px;border-radius:50%;display:flex;align-items:center;justify-content:center;">
        </div>
        <div class="rw-modal-title" id="rwToggleTitle" style="margin-bottom:8px;"></div>
        <p style="color:#64748b;font-size:13.5px;margin:0 0 6px;font-weight:600;" id="rwToggleRewardName"></p>
        <p style="color:#94a3b8;font-size:12px;margin:0 0 24px;" id="rwToggleNote"></p>
        <div class="rw-modal-actions" style="justify-content:center;">
            <button class="btn-outline" onclick="closeToggleRewardModal()">Cancel</button>
            <button class="btn" id="rwToggleBtn" onclick="confirmToggleReward()"></button>
        </div>
    </div>
</div>

<!-- Delete Confirm Modal -->
<div class="rw-modal-overlay" id="rwDeleteModal">
    <div class="rw-modal" style="max-width:400px;text-align:center;">
        <div style="padding-top:8px;">
            <div
                style="width:56px;height:56px;margin:0 auto 14px;border-radius:50%;background:#fee2e2;display:flex;align-items:center;justify-content:center;">
                <svg fill="none" stroke="#dc2626" stroke-width="2" viewBox="0 0 24 24" width="26" height="26">
                    <polyline points="3 6 5 6 21 6" />
                    <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6" />
                    <path d="M10 11v6M14 11v6" />
                    <path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2" />
                </svg>
            </div>
            <div class="rw-modal-title" style="margin-bottom:10px;">Delete Reward?</div>
            <p style="color:#64748b;font-size:13.5px;margin:0 0 6px;font-weight:600;" id="rwDeleteName"></p>
            <p style="color:#94a3b8;font-size:12px;margin:0 0 24px;">This cannot be undone. Existing redemptions will
                not be affected.</p>
        </div>
        <div class="rw-modal-actions" style="justify-content:center;">
            <button class="btn-outline" onclick="closeDeleteModal()">Cancel</button>
            <button class="btn btn-primary" style="background:#dc2626;box-shadow:0 2px 8px rgba(220,38,38,.25);"
                id="rwDeleteBtn" onclick="confirmDelete()">Delete</button>
        </div>
    </div>
</div>

<script src="../../assets/js/admin/loyalty_rewards.js"></script>

<?php include '../../includes/layout_close.php'; ?>