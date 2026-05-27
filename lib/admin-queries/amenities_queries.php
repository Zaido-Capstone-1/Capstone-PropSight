<?php
/**
 * lib/admin/amenities_data.php
 * Data layer for pages/admin/amenities.php
 * Requires: $conn (mysqli)
 */

// Stat snapshot (no user input)
$stats = $conn->query(
    "SELECT COUNT(*) AS total,
            SUM(status='available')   AS available,
            SUM(status='unavailable') AS unavailable,
            SUM(status='maintenance') AS maintenance
     FROM amenities"
)->fetch_assoc();

$total = (int) ($stats['total'] ?? 0);
$available = (int) ($stats['available'] ?? 0);
$unavailable = (int) ($stats['unavailable'] ?? 0);
$maintenance = (int) ($stats['maintenance'] ?? 0);

// Properties list (no user input)
$properties = $conn->query(
    "SELECT property_id, property_name FROM properties ORDER BY property_name ASC"
)->fetch_all(MYSQLI_ASSOC);

// Amenities grouped by property — fix: raw $pid → prepared statement
$grouped = [];
$stmtAm = $conn->prepare(
    "SELECT * FROM amenities WHERE property_id=? ORDER BY name ASC"
);
foreach ($properties as $prop) {
    $pid = (int) $prop['property_id'];
    $stmtAm->bind_param('i', $pid);
    $stmtAm->execute();
    $items = $stmtAm->get_result()->fetch_all(MYSQLI_ASSOC);
    $grouped[$pid] = ['property_name' => $prop['property_name'], 'items' => $items];
}
$stmtAm->close();

function am_svg(string $key): string
{
    $p = [
        'pool' => '<path d="M2 12h20M2 17c2-2 4-2 6 0s4 2 6 0 4-2 6 0M7 12V7a5 5 0 0 1 10 0v5"/>',
        'gym' => '<path d="M6 4v16M18 4v16M4 8h4M16 8h4M4 16h4M16 16h4M8 12h8"/>',
        'parking' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 17V7h4a3 3 0 0 1 0 6H9"/>',
        'security' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'wifi' => '<path d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"/>',
        'cafe' => '<path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/>',
        'gameroom' => '<line x1="6" y1="11" x2="10" y2="11"/><line x1="8" y1="9" x2="8" y2="13"/><line x1="15" y1="12" x2="15.01" y2="12"/><line x1="17" y1="10" x2="17.01" y2="10"/><rect x="2" y="6" width="20" height="12" rx="2"/>',
        'storage' => '<path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
        'garden' => '<path d="M12 22V12M12 12C12 7 7 3 3 3c0 4 2 8 9 9M12 12c0-5 5-9 9-9-1 4-4 8-9 9"/>',
        'laundry' => '<rect x="2" y="2" width="20" height="20" rx="2"/><circle cx="12" cy="13" r="5"/><circle cx="12" cy="13" r="2"/><path d="M8 6h.01M11 6h.01"/>',
        'elevator' => '<rect x="3" y="2" width="18" height="20" rx="2"/><path d="M9 2v20M15 7l-3-3-3 3M15 17l-3 3-3-3"/>',
        'playground' => '<circle cx="12" cy="8" r="3"/><path d="M5 20a7 7 0 0 1 14 0"/><line x1="12" y1="11" x2="12" y2="14"/>',
        'bbq' => '<path d="M4 11h16M12 11V4M6 11l-2 9M18 11l2 9M9 20h6"/><circle cx="12" cy="4" r="1"/>',
        'cctv' => '<path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2"/>',
        'rooftop' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'clubhouse' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'spa' => '<path d="M12 22c-4.97 0-9-2.69-9-6 0-1.5.75-2.87 2-3.9C6.56 10.85 9.12 10 12 10s5.44.85 7 2.1C20.25 13.13 21 14.5 21 16c0 3.31-4.03 6-9 6z"/><path d="M12 10C9 7 7 4 9 2c1 2 4 3 3 8M12 10c3-3 5-6 3-8-1 2-4 3-3 8"/>',
        'generator' => '<circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/>',
        'trash' => '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6M10 11v6M14 11v6M9 6V4h6v2"/>',
        'water' => '<path d="M12 2c0 6-8 10-8 14a8 8 0 0 0 16 0c0-4-8-8-8-14z"/>',
        'balcony' => '<rect x="3" y="11" width="18" height="10" rx="1"/><path d="M3 11V7a9 9 0 0 1 18 0v4M9 21v-4a3 3 0 0 1 6 0v4"/>',
        'ac' => '<rect x="2" y="5" width="20" height="8" rx="2"/><path d="M7 13v4M12 13v4M17 13v4M7 17H5M12 17h-2M17 17h-2"/>',
        'shower' => '<path d="M4 4h2a8 8 0 0 1 16 0v2"/><path d="M6.5 13.5c1 1 1 2.5 0 3.5s-2.5.5-3 2"/><line x1="18" y1="6" x2="6" y2="18"/>',
    ];
    $path = $p[$key] ?? $p['security'];
    return '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:20px;height:20px;">' . $path . '</svg>';
}