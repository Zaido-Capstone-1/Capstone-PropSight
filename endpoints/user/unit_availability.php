<?php
/**
 * endpoints/user/unit_availability.php
 *
 * Returns units available on a given date, optionally filtered by property.
 *
 * GET  ?date=YYYY-MM-DD[&property_id=INT]
 */

require_once __DIR__ . '/../../includes/session_params.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

// ── Input validation ──────────────────────────────────────────────────────────
$rawDate = trim($_GET['date'] ?? '');
if (!$rawDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)) {
    http_response_code(400);
    echo json_encode(['error' => 'A valid date (YYYY-MM-DD) is required.']);
    exit;
}

$queryDate  = $rawDate;
$propertyId = isset($_GET['property_id']) && ctype_digit((string)$_GET['property_id'])
              ? (int)$_GET['property_id'] : null;

// ── Also return all properties for the picker dropdown ───────────────────────
$propertiesOut = [];
$propRes = mysqli_query($conn, "
    SELECT p.property_id, p.property_name, p.city, p.address
    FROM   properties p
    INNER JOIN units u ON u.property_id = p.property_id
    WHERE  u.status NOT IN ('inactive','maintenance')
    GROUP BY p.property_id
    ORDER BY p.property_name ASC
");
while ($pr = mysqli_fetch_assoc($propRes)) {
    $propertiesOut[] = [
        'property_id'   => (int)$pr['property_id'],
        'property_name' => $pr['property_name'],
        'city'          => $pr['city'] ?? '',
        'address'       => $pr['address'] ?? '',
    ];
}

// ── Property filter clause ────────────────────────────────────────────────────
$propClause = $propertyId ? "AND u.property_id = $propertyId" : '';

// ── Fetch available units ─────────────────────────────────────────────────────
$sql = "
    SELECT
        u.unit_id,
        u.unit_number,
        u.unit_name,
        u.unit_type,
        u.status,
        u.floor,
        u.rent_amount,
        u.description,
        p.property_id,
        p.property_name,
        p.address,
        p.city,
        p.latitude,
        p.longitude,
        (
            SELECT ui.image_path
            FROM unit_images ui
            WHERE ui.unit_id = u.unit_id
            ORDER BY ui.sort_order ASC, ui.image_id ASC
            LIMIT 1
        ) AS image_path,
        (
            SELECT MIN(b2.checkin_date)
            FROM bookings b2
            WHERE b2.unit_id = u.unit_id
              AND b2.status IN ('pending','confirmed','active')
              AND b2.checkin_date > ?
        ) AS available_until
    FROM units u
    JOIN properties p ON p.property_id = u.property_id
    WHERE u.status NOT IN ('inactive','maintenance')
      $propClause
      AND u.unit_id NOT IN (
          SELECT b.unit_id
          FROM bookings b
          WHERE b.status IN ('pending','confirmed','active')
            AND b.checkin_date  <= ?
            AND b.checkout_date >  ?
      )
    ORDER BY p.property_name ASC, u.rent_amount ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('sss', $queryDate, $queryDate, $queryDate);
$stmt->execute();
$result = $stmt->get_result();

$units   = [];
$unitIds = [];
while ($row = $result->fetch_assoc()) {
    $units[]   = $row;
    $unitIds[] = (int)$row['unit_id'];
}
$stmt->close();

// ── Amenities ─────────────────────────────────────────────────────────────────
$amenitiesMap = [];
if (!empty($unitIds)) {
    $inList = implode(',', $unitIds);
    $amRes  = mysqli_query($conn, "
        SELECT ua.unit_id, a.name
        FROM unit_amenities ua
        JOIN amenities a ON a.amenity_id = ua.amenity_id
        WHERE ua.unit_id IN ($inList)
        ORDER BY a.name ASC
    ");
    while ($amRow = mysqli_fetch_assoc($amRes)) {
        $amenitiesMap[(int)$amRow['unit_id']][] = $amRow['name'];
    }
}

// ── Build response ────────────────────────────────────────────────────────────
$out = [];
foreach ($units as $u) {
    $uid = (int)$u['unit_id'];

    $rawNum = trim(preg_replace('/^unit\s*/i', '', $u['unit_number'] ?? ''));
    if      (!empty($u['unit_name']))                         $displayName = $u['unit_name'];
    elseif  (!empty($u['property_name']) && $rawNum !== '')   $displayName = $u['property_name'] . ' — Unit ' . $rawNum;
    elseif  (!empty($u['unit_number']))                       $displayName = $u['unit_number'];
    else                                                       $displayName = 'Unit #' . $uid;

    $imgPath = '';
    if (!empty($u['image_path'])) {
        $imgPath = preg_replace('#^(\.\./)+#', '', ltrim($u['image_path'], '/'));
    }

    $out[] = [
        'unit_id'         => $uid,
        'name'            => $displayName,
        'unit_type'       => $u['unit_type'] ?? 'Standard',
        'property_id'     => (int)$u['property_id'],
        'property_name'   => $u['property_name'] ?? '',
        'address'         => $u['address'] ?? '',
        'city'            => $u['city'] ?? '',
        'floor'           => $u['floor'] ?? null,
        'rent_amount'     => (float)$u['rent_amount'],
        'description'     => $u['description'] ?? '',
        'image_path'      => $imgPath,
        'amenities'       => $amenitiesMap[$uid] ?? [],
        'available_until' => $u['available_until'],
    ];
}

echo json_encode([
    'date'       => $queryDate,
    'property_id'=> $propertyId,
    'properties' => $propertiesOut,
    'units'      => $out,
]);