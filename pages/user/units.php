<?php
/* ═══════════════════════════════════════════════════════════════════════════
   pages/user/units.php  —  Browse All Units
   ═══════════════════════════════════════════════════════════════════════════ */

include '../../includes/session.php';
require_not_blacklisted(false);

if ($_SESSION['role'] !== 'user') {
    echo '<!DOCTYPE html><html><body><script>setTimeout(() => history.back(), 2000);</script>
</body></html>';
    exit;
}

include '../../includes/fetch_units.php';
require_once '../../includes/db.php';

$_uid = (int) $_SESSION['user_id'];

// ── Saved unit IDs ──────────────────────────────────────────────────────────
$_savedRes = mysqli_query($conn, "SELECT unit_id FROM saved_units WHERE user_id=$_uid");
$savedUnitIds = [];
while ($_sr = mysqli_fetch_assoc($_savedRes))
    $savedUnitIds[] = (int) $_sr['unit_id'];

// ── User profile photo + initials ───────────────────────────────────────────
$_uRow = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT first_name, last_name, profile_photo FROM users WHERE user_id=$_uid"
));
$_photo = !empty($_uRow['profile_photo']) ? '../../' . ltrim($_uRow['profile_photo'], '/') : '';
$_initials = strtoupper(mb_substr($_uRow['first_name'] ?? '', 0, 1)
    . mb_substr($_uRow['last_name'] ?? '', 0, 1));

// ── Room type filter list ────────────────────────────────────────────────────
$roomTypeFilters = [];
foreach ($units as $u) {
    $t = trim((string) ($u['unit_type'] ?? ''));
    if (!$t)
        continue;
    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($t));
    $roomTypeFilters[$slug] = $roomTypeFilters[$slug] ?? $t;
}

// ── All unique amenities across all units ────────────────────────────────────
$allAmenities = [];
foreach ($amenitiesMap ?? [] as $ams) {
    foreach ($ams as $a) {
        $n = is_array($a) ? ($a['name'] ?? '') : $a;
        if ($n && !in_array($n, $allAmenities))
            $allAmenities[] = $n;
    }
}
sort($allAmenities);

// ── Price range bounds ───────────────────────────────────────────────────────
$allPrices = array_column($units, 'rent_amount');
$priceMin = !empty($allPrices) ? (int) min($allPrices) : 0;
$priceMax = !empty($allPrices) ? (int) max($allPrices) : 99999;

// ── Helpers ──────────────────────────────────────────────────────────────────
function unitTypeToCategory(string $type): string
{
    $t = strtolower(trim($type));
    $map = ['studio', 'deluxe', 'suite', 'family', 'standard', 'penthouse'];
    $out = [];
    foreach ($map as $k) {
        if (strpos($t, $k) !== false)
            $out[] = $k;
    }
    return implode(' ', $out) ?: 'standard';
}

$seasonColor = ['Peak' => '#c0694a', 'High' => '#c9a84c', 'Low' => '#2ECC71'];
$totalUnits = count($units);
$vacantCount = count(array_filter($units, fn($u) => $u['status'] === 'vacant'));

// ── Per-type counts ──────────────────────────────────────────────────────────
$typeCounts = [];
foreach ($units as $u) {
    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($u['unit_type'] ?? ''));
    $typeCounts[$slug] = ($typeCounts[$slug] ?? 0) + 1;
}

// ── Per-amenity counts ───────────────────────────────────────────────────────
$amenityCounts = [];
foreach ($amenitiesMap ?? [] as $ams) {
    foreach ($ams as $a) {
        $n = is_array($a) ? ($a['name'] ?? '') : $a;
        if ($n)
            $amenityCounts[$n] = ($amenityCounts[$n] ?? 0) + 1;
    }
}

// ── Per-season counts ────────────────────────────────────────────────────────
$seasonCounts = [];
foreach ($units as $u) {
    $s = $u['season'] ?? 'Low';
    $seasonCounts[$s] = ($seasonCounts[$s] ?? 0) + 1;
}

// ── Floor list ───────────────────────────────────────────────────────────────
$floors = [];
foreach ($units as $u) {
    if ($u['floor'])
        $floors[(int) $u['floor']] = true;
}
ksort($floors);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Units — Boracay Accommodation</title>
    <link rel="icon" type="image/png" href="../../assets/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="../../assets/css/user-css/layout.css">
    <link rel="stylesheet" href="../../assets/css/user-css/bottom-nav.css">
    <link rel="stylesheet" href="../../assets/css/user-css/styles.css">
    <link rel="stylesheet" href="../../assets/css/user-css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/user-css/units.css">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">

    <!-- Pass PHP config to JS -->
    <script>
        window.UNITS_CONFIG = {
            priceMin: <?php echo $priceMin; ?>,
            priceMax: <?php echo $priceMax; ?>
        };
    </script>
</head>

<body>

<!-- ══ HEADER ════════════════════════════════════════════════════════════════ -->
<header id="hdr">
    <a href="user-dashboard.php" class="logo">
        <img src="../../assets/images/logo.png" alt="Boracay Accommodation" class="logo-icon">
        <div class="logo-wordmark">
            <strong>Boracay Accommodation</strong>
            <span>Boracay, Philippines</span>
        </div>
    </a>
    <nav>
        <a href="user-dashboard.php">Dashboard</a>
        <a href="bookings.php">My Bookings</a>
        <a href="units.php" class="active">Browse Units</a>
        <a href="saved.php">Saved</a>
    </nav>
    <div class="header-right">
        <div class="btn-profile-wrap">
            <button class="btn-profile" aria-label="My Profile" onclick="location.href='profile.php'">
                <?php if ($_photo): ?>
                        <img src="<?php echo htmlspecialchars($_photo); ?>" alt="Profile"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='inline'">
                <?php endif; ?>
                <span class="profile-initials" <?php echo $_photo ? 'style="display:none;"' : ''; ?>>
                    <?php echo htmlspecialchars($_initials); ?>
                </span>
            </button>
        </div>
        <button class="hamburger" id="hamburger" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
    </div>
</header>

<!-- ══ HERO ═════════════════════════════════════════════════════════════════ -->
<section class="units-hero">
    <div class="units-hero-inner">
        <div class="units-hero-text">
            <a href="user-dashboard.php" class="units-back">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Back to Dashboard
            </a>
            <div class="units-hero-eyebrow"><span></span>All Properties<span></span></div>
            <h1>Browse Our <em>Units</em></h1>
            <p>Filter, sort, and find the perfect unit for your Boracay stay.</p>
        </div>
        <div class="units-hero-stats">
            <div class="uhs-stat">
                <div class="uhs-num"><?php echo $totalUnits; ?></div>
                <div class="uhs-lbl">Total Units</div>
            </div>
            <div class="uhs-stat">
                <div class="uhs-num" style="color:#4ade80;"><?php echo $vacantCount; ?></div>
                <div class="uhs-lbl">Available Now</div>
            </div>
        </div>
    </div>
</section>

<!-- ══ TOOLBAR ════════════════════════════════════════════════════════════════ -->
<div class="units-toolbar">
    <div class="units-toolbar-inner">

        <!-- Mobile-only filter button -->
        <button class="btn-filter-toggle" id="filterToggleBtn" onclick="openSidebar()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="4" y1="6" x2="20" y2="6"/>
                <line x1="8" y1="12" x2="16" y2="12"/>
                <line x1="11" y1="18" x2="13" y2="18"/>
            </svg>
            Filters
            <span class="f-badge" id="mobileFilterCount">0</span>
        </button>

        <!-- Search -->
        <div class="u-search-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/>
                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input type="text" id="unitSearch" placeholder="Search units, properties…"
                   oninput="applyFilters()" autocomplete="off">
        </div>

        <!-- Sort -->
        <select class="u-sort-select" id="unitSort" onchange="applySort(this.value)">
            <option value="default">Sort: Default</option>
            <option value="price-asc">Price: Low → High</option>
            <option value="price-desc">Price: High → Low</option>
            <option value="name-asc">Name: A → Z</option>
            <option value="rating-desc">Top Rated</option>
        </select>

        <!-- View toggle -->
        <div class="u-view-toggle">
            <button class="u-view-btn active" id="viewGrid" aria-label="Grid view" onclick="setView('grid')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                    <rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>
                </svg>
            </button>
            <button class="u-view-btn" id="viewList" aria-label="List view" onclick="setView('list')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="8" y1="6" x2="21" y2="6"/>
                    <line x1="8" y1="12" x2="21" y2="12"/>
                    <line x1="8" y1="18" x2="21" y2="18"/>
                    <line x1="3" y1="6" x2="3.01" y2="6"/>
                    <line x1="3" y1="12" x2="3.01" y2="12"/>
                    <line x1="3" y1="18" x2="3.01" y2="18"/>
                </svg>
            </button>
        </div>
    </div>
</div>

<!-- Mobile backdrop for sidebar drawer -->
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeSidebar()"></div>

<!-- ══ PAGE BODY (sidebar + grid) ═══════════════════════════════════════════ -->
<div class="units-body">

    <!-- ── LEFT SIDEBAR ───────────────────────────────────────────────────── -->
    <aside class="units-sidebar" id="unitsSidebar">

        <div class="sb-head">
            <span class="sb-head-title">Filters</span>
            <button class="sb-clear-btn" id="sbClearBtn" onclick="clearAllFilters()">Clear all</button>
        </div>

        <!-- ① Availability ------------------------------------------------- -->
        <div class="sb-section">
            <div class="sb-section-label">Availability</div>
            <div class="sb-avail-btns">
                <button class="sb-avail-btn active" data-avail="all" onclick="setAvail('all',this)">
                    <span class="sb-avail-dot" style="background:var(--navy-300);"></span>
                    All Units
                    <span class="sb-avail-count"><?php echo $totalUnits; ?></span>
                </button>
                <button class="sb-avail-btn" data-avail="vacant" onclick="setAvail('vacant',this)">
                    <span class="sb-avail-dot" style="background:#4ade80;"></span>
                    Available Now
                    <span class="sb-avail-count"><?php echo $vacantCount; ?></span>
                </button>
                <button class="sb-avail-btn" data-avail="booked" onclick="setAvail('booked',this)">
                    <span class="sb-avail-dot" style="background:var(--terra);"></span>
                    Booked
                    <span class="sb-avail-count"><?php echo $totalUnits - $vacantCount; ?></span>
                </button>
            </div>
        </div>

        <!-- ② Price Range --------------------------------------------------- -->
        <div class="sb-section">
            <div class="sb-section-label">
                Price / Night
                <button onclick="resetPrice()">Reset</button>
            </div>
            <div class="sb-price-display">
                <span class="sb-price-val" id="priceMinDisplay">₱<?php echo number_format($priceMin); ?></span>
                <span class="sb-price-sep">—</span>
                <span class="sb-price-val" id="priceMaxDisplay">₱<?php echo number_format($priceMax); ?></span>
            </div>
            <div class="sb-range-wrap">
                <div class="sb-range-track"></div>
                <div class="sb-range-fill" id="rangeFill"></div>
                <input type="range" class="sb-range-input" id="priceRangeMin"
                       min="<?php echo $priceMin; ?>" max="<?php echo $priceMax; ?>"
                       value="<?php echo $priceMin; ?>" oninput="onPriceRange()">
                <input type="range" class="sb-range-input" id="priceRangeMax"
                       min="<?php echo $priceMin; ?>" max="<?php echo $priceMax; ?>"
                       value="<?php echo $priceMax; ?>" oninput="onPriceRange()">
            </div>
        </div>

        <!-- ③ Unit Type ----------------------------------------------------- -->
        <?php if (!empty($roomTypeFilters)): ?>
            <div class="sb-section">
                <div class="sb-section-label">
                    Unit Type
                    <button onclick="clearTypeFilters()">Clear</button>
                </div>
                <div class="sb-check-list">
                    <?php foreach ($roomTypeFilters as $slug => $label): ?>
                            <label class="sb-check-item">
                                <input type="checkbox" class="type-cb"
                                       value="<?php echo htmlspecialchars($slug); ?>"
                                       onchange="applyFilters()">
                                <span class="sb-check-label"><?php echo htmlspecialchars($label); ?></span>
                                <span class="sb-check-count"><?php echo $typeCounts[$slug] ?? 0; ?></span>
                            </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ④ Floor --------------------------------------------------------- -->
        <?php if (!empty($floors)): ?>
            <div class="sb-section">
                <div class="sb-section-label">
                    Floor
                    <button onclick="clearFloorFilters()">Clear</button>
                </div>
                <div class="sb-floor-btns">
                    <?php foreach (array_keys($floors) as $flr): ?>
                            <button class="sb-floor-btn" data-floor="<?php echo $flr; ?>"
                                    onclick="toggleFloor(<?php echo $flr; ?>, this)">
                                Floor <?php echo $flr; ?>
                            </button>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ⑤ Season -------------------------------------------------------- -->
        <?php
        $seasonDots = ['Low' => '#4ade80', 'High' => '#c9a84c', 'Peak' => '#c0694a'];
        $hasSeasons = !empty(array_filter($seasonDots, fn($s) => ($seasonCounts[$s] ?? 0) > 0, ARRAY_FILTER_USE_KEY));
        if ($hasSeasons): ?>
            <div class="sb-section">
                <div class="sb-section-label">Season</div>
                <div class="sb-season-btns">
                    <?php foreach ($seasonDots as $s => $col):
                        $cnt = $seasonCounts[$s] ?? 0;
                        if (!$cnt)
                            continue; ?>
                            <button class="sb-season-btn" data-season="<?php echo $s; ?>"
                                    onclick="toggleSeason('<?php echo $s; ?>', this)">
                                <span class="sb-season-dot" style="background:<?php echo $col; ?>;"></span>
                                <?php echo $s; ?> Season
                                <span class="sb-avail-count"><?php echo $cnt; ?></span>
                            </button>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ⑥ Amenities ----------------------------------------------------- -->
        <?php if (!empty($allAmenities)): ?>
            <div class="sb-section">
                <div class="sb-section-label">
                    Amenities
                    <button onclick="clearAmenityFilters()">Clear</button>
                </div>
                <div class="sb-amenity-list">
                    <?php foreach ($allAmenities as $am): ?>
                            <label class="sb-check-item">
                                <input type="checkbox" class="amenity-cb"
                                       value="<?php echo htmlspecialchars($am); ?>"
                                       onchange="applyFilters()">
                                <span class="sb-check-label"><?php echo htmlspecialchars($am); ?></span>
                                <span class="sb-check-count"><?php echo $amenityCounts[$am] ?? 0; ?></span>
                            </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    </aside><!-- /units-sidebar -->

    <!-- ── RIGHT: RESULTS ─────────────────────────────────────────────────── -->
    <div class="units-main">

        <!-- Meta row -->
        <div class="units-meta-row">
            <p class="units-result-count">
                Showing <strong id="unitsCountNum"><?php echo $totalUnits; ?></strong>
                of <strong><?php echo $totalUnits; ?></strong>
                unit<?php echo $totalUnits !== 1 ? 's' : ''; ?>
            </p>
            <div class="units-active-tags" id="activeTagsWrap"></div>
        </div>

        <!-- Card grid -->
        <div id="unitsGrid">

            <?php if (empty($units)): ?>
                    <div class="units-empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                        </svg>
                        <h3>No units yet</h3>
                        <p>There are no units available at the moment. Please check back later.</p>
                    </div>

            <?php else: ?>

                    <?php foreach ($units as $unit):
                        $isVacant = $unit['status'] === 'vacant';
                        $cats = unitTypeToCategory($unit['unit_type'] ?? '');
                        $rawNum = trim(preg_replace('/^unit\s*/i', '', $unit['unit_number'] ?? ''));

                        if (!empty($unit['unit_name']))
                            $rawName = $unit['unit_name'];
                        elseif (!empty($unit['property_name']) && $rawNum !== '')
                            $rawName = $unit['property_name'] . ' — Unit ' . $rawNum;
                        elseif (!empty($unit['unit_number']))
                            $rawName = $unit['unit_number'];
                        else
                            $rawName = 'Unit #' . $unit['unit_id'];

                        $unitName = htmlspecialchars($rawName);
                        $propName = htmlspecialchars($unit['property_name'] ?? '');
                        $cityPart = !empty($unit['city']) ? ', ' . $unit['city'] : '';
                        $baseRate = (float) $unit['rent_amount'];
                        $unitSeason = $unit['season'] ?? 'Low';
                        $sColor = $seasonColor[$unitSeason] ?? '#2ECC71';

                        $unitAms = $amenitiesMap[$unit['unit_id']] ?? [];
                        $amNames = array_values(array_filter(
                            array_map(fn($a) => is_array($a) ? ($a['name'] ?? '') : $a, $unitAms)
                        ));

                        $imgSrc = $unit['image_path'] ? '../../' . ltrim($unit['image_path'], '/') : '';
                        $isSaved = in_array((int) $unit['unit_id'], $savedUnitIds);
                        $ratingValue = isset($unit['rating']) && $unit['rating'] !== null && $unit['rating'] !== ''
                            ? round((float) $unit['rating'], 1) : null;

                        $availClass = $isVacant ? 'avail-yes'
                            : ($unit['status'] === 'maintenance' ? 'avail-maintenance' : 'avail-no');
                        $detailUrl = 'unit_detail.php?id=' . (int) $unit['unit_id'];
                        $bookUrl = 'unit_detail.php?id=' . (int) $unit['unit_id'] . '&book=1';
                        $typeSlug = preg_replace('/[^a-z0-9]+/', '-', strtolower($unit['unit_type'] ?? ''));
                        $amAttr = htmlspecialchars(implode('||', $amNames), ENT_QUOTES);
                        ?>
                        <div class="room-card"
                             data-unit-id="<?php echo (int) $unit['unit_id']; ?>"
                             data-cat="<?php echo htmlspecialchars($cats, ENT_QUOTES); ?>"
                             data-type="<?php echo htmlspecialchars($typeSlug, ENT_QUOTES); ?>"
                             data-name="<?php echo htmlspecialchars(strtolower($rawName . ' ' . ($unit['property_name'] ?? '')), ENT_QUOTES); ?>"
                             data-status="<?php echo htmlspecialchars($unit['status'], ENT_QUOTES); ?>"
                             data-rent="<?php echo $baseRate; ?>"
                             data-rating="<?php echo $ratingValue ?? 0; ?>"
                             data-floor="<?php echo (int) ($unit['floor'] ?? 0); ?>"
                             data-season="<?php echo htmlspecialchars($unitSeason, ENT_QUOTES); ?>"
                             data-amenities="<?php echo $amAttr; ?>"
                             onclick="location.href='<?php echo $detailUrl; ?>'">

                            <!-- Image -->
                            <div class="room-card-img">
                                <?php if ($imgSrc): ?>
                                        <img src="<?php echo $imgSrc; ?>" alt="<?php echo $unitName; ?>"
                                             class="room-img-placeholder"
                                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                <?php endif; ?>
                                <!-- Fallback -->
                                <div style="<?php echo $imgSrc ? 'display:none;' : ''; ?>width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--navy-700),var(--navy-500));color:rgba(255,255,255,.28);">
                                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                        <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                                    </svg>
                                </div>

                                <span class="room-badge-img badge-blue">
                                    <?php echo htmlspecialchars(strtoupper($unit['unit_type'] ?? 'UNIT')); ?>
                                </span>

                                <span class="room-avail <?php echo $availClass; ?>">
                                    <?php if ($isVacant): ?>✓ Available
                                    <?php elseif ($unit['status'] === 'maintenance'): ?>Maintenance
                                    <?php else: ?>Booked<?php endif; ?>
                                </span>

                                <button class="btn-save-room<?php echo $isSaved ? ' saved' : ''; ?>"
                                        onclick="event.stopPropagation(); toggleSaveRoom(<?php echo (int) $unit['unit_id']; ?>, this)"
                                        aria-label="<?php echo $isSaved ? 'Remove from saved' : 'Save room'; ?>">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
                                    </svg>
                                </button>

                                <div class="room-season-vignette">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                                    </svg>
                                    <?php echo ucfirst($unitSeason); ?> Season
                                    <span style="margin-left:auto;font-weight:700;color:<?php echo $sColor; ?>;">●</span>
                                </div>
                            </div><!-- /room-card-img -->

                            <!-- Body -->
                            <div class="room-card-body">
                                <div class="room-card-top">
                                    <div class="room-name"><?php echo $unitName; ?></div>
                                    <div class="room-rating<?php echo $ratingValue === null ? ' no-rating' : ''; ?>">
                                        <?php if ($ratingValue !== null): ?>
                                                <svg viewBox="0 0 24 24">
                                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                                </svg>
                                                <span><?php echo number_format($ratingValue, 1); ?></span>
                                        <?php else: ?>
                                                <span>No ratings yet.</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($propName || $cityPart): ?>
                                        <div class="room-location-chip">
                                            <svg viewBox="0 0 24 24">
                                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/>
                                                <circle cx="12" cy="10" r="3"/>
                                            </svg>
                                            <?php echo $propName . htmlspecialchars($cityPart); ?>
                                        </div>
                                <?php endif; ?>

                                <div class="room-meta">
                                    <span>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                                        </svg>
                                        <?php echo htmlspecialchars(ucfirst($unit['unit_type'] ?? 'Standard')); ?>
                                    </span>
                                    <?php if ($unit['floor']): ?>
                                            <span>
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                    <rect x="2" y="7" width="20" height="14" rx="2"/>
                                                    <path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/>
                                                </svg>
                                                Floor <?php echo (int) $unit['floor']; ?>
                                            </span>
                                    <?php endif; ?>
                                    <span>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                                            <circle cx="9" cy="7" r="4"/>
                                        </svg>
                                        2 Guests
                                    </span>
                                </div>

                                <?php if (!empty($amNames)): ?>
                                        <div class="room-features">
                                            <?php foreach (array_slice($amNames, 0, 4) as $am): ?>
                                                    <span class="feature-chip"><?php echo htmlspecialchars($am); ?></span>
                                            <?php endforeach; ?>
                                            <?php if (count($amNames) > 4): ?>
                                                    <span class="feature-chip" style="opacity:.55;">+<?php echo count($amNames) - 4; ?> more</span>
                                            <?php endif; ?>
                                        </div>
                                <?php endif; ?>

                                <div class="room-divider"></div>

                                <div class="room-price-row">
                                    <div class="room-price">
                                        ₱<?php echo number_format((int) $baseRate); ?> <sub>/ night</sub>
                                    </div>
                                    <div style="display:flex;gap:8px;align-items:center;" onclick="event.stopPropagation()">
                                        <button class="btn-view-details"
                                                onclick="location.href='<?php echo $detailUrl; ?>'">
                                            Details
                                        </button>
                                        <?php if ($isVacant): ?>
                                                <button class="btn-rent"
                                                        onclick="location.href='<?php echo $bookUrl; ?>'">
                                                    Book Now
                                                </button>
                                        <?php else: ?>
                                                <button class="btn-rent" disabled>Unavailable</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div><!-- /room-card-body -->

                        </div><!-- /room-card -->
                    <?php endforeach; ?>

                    <!-- Empty state when all filtered out -->
                    <div id="unitsEmptyFallback" class="units-empty-state" style="display:none;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <circle cx="11" cy="11" r="8"/>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <h3>No units found</h3>
                        <p>No units match your current filters. Try adjusting them.</p>
                        <button onclick="clearAllFilters()">Clear All Filters</button>
                    </div>

            <?php endif; ?>

        </div><!-- /#unitsGrid -->
    </div><!-- /.units-main -->
</div><!-- /.units-body -->

<script src="../../assets/js/user-js/units.js"></script>

<?php require '../../includes/_bottom_nav.php'; ?>
</body>
</html>