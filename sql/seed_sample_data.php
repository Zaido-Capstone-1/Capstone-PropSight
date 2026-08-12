<?php
// Run once: php sql/seed_sample_data.php
// Adds 10 sample users + tenants, 50 sample bookings, and 50 matching payments
// against the existing Railway database (uses the app's own includes/db.php).

require_once __DIR__ . '/../includes/db.php';
$conn = createDatabaseConnection();

$firstNames = ['Liam', 'Sophia', 'Noah', 'Ava', 'Mateo', 'Isabella', 'Kenji', 'Amara', 'Lucas', 'Mei'];
$lastNames = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Garcia', 'Torres', 'Dela Cruz', 'Ramos', 'Flores', 'Castro'];
$nationalities = ['Philippines', 'South Korea', 'Japan', 'Australia', 'United States', 'Singapore', 'Germany', 'United Kingdom', 'Canada', 'China'];

$userIds = [];
$tenantIds = [];
$password = password_hash('Sample@1234', PASSWORD_DEFAULT);

for ($i = 0; $i < 10; $i++) {
    $first = $firstNames[$i];
    $last = $lastNames[$i];
    $email = strtolower($first . '.' . $last) . '.sample' . $i . '@example.com';
    $phone = '9' . str_pad((string) mt_rand(100000000, 999999999), 9, '0', STR_PAD_LEFT);
    $nationality = $nationalities[$i];
    $role = 'user';
    $verification = ($i % 3 === 0) ? 'Verified' : 'Not Verified';

    $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, phone, nationality, password, role, verification_status, is_active, created_at) VALUES (?,?,?,?,?,?,?,?,1,NOW())");
    $stmt->bind_param('ssssssss', $first, $last, $email, $phone, $nationality, $password, $role, $verification);
    $stmt->execute();
    $userIds[] = $conn->insert_id;
    $stmt->close();

    $fullName = $first . ' ' . $last;
    $tStmt = $conn->prepare("INSERT INTO tenants (full_name, phone, email, move_in_date, created_at) VALUES (?,?,?,CURDATE(),NOW())");
    $tStmt->bind_param('sss', $fullName, $phone, $email);
    $tStmt->execute();
    $tenantIds[] = $conn->insert_id;
    $tStmt->close();
}

$units = [];
$res = $conn->query("SELECT unit_id, rent_amount FROM units");
while ($row = $res->fetch_assoc()) {
    $units[] = $row;
}

$statuses = ['pending', 'confirmed', 'active', 'completed', 'cancelled'];
$methods = ['Cash', 'GCash', 'Maya', 'Card'];
$sources = ['Direct', 'Direct', 'Direct', 'Airbnb', 'Booking.com'];

for ($i = 0; $i < 50; $i++) {
    $idx = $i % 10;
    $userId = $userIds[$idx];
    $tenantId = $tenantIds[$idx];
    $unit = $units[array_rand($units)];
    $nights = mt_rand(1, 7);
    $checkin = date('Y-m-d', strtotime('+' . mt_rand(-30, 60) . ' days'));
    $checkout = date('Y-m-d', strtotime($checkin . ' +' . $nights . ' days'));
    $guests = mt_rand(1, 6);
    $total = round(((float) $unit['rent_amount']) * $nights, 2);
    $status = $statuses[array_rand($statuses)];
    $method = $methods[array_rand($methods)];
    $source = $sources[array_rand($sources)];
    $unitId = (int) $unit['unit_id'];

    $stmt = $conn->prepare("INSERT INTO bookings (unit_id, tenant_id, user_id, checkin_date, checkout_date, guests, total_amount, status, payment_method, booking_source, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
    $stmt->bind_param('iiissidsss', $unitId, $tenantId, $userId, $checkin, $checkout, $guests, $total, $status, $method, $source);
    $stmt->execute();
    $bookingId = $conn->insert_id;
    $stmt->close();

    $payStatus = ($status === 'cancelled') ? 'pending' : (mt_rand(0, 9) < 8 ? 'paid' : 'pending');
    $notes = 'Sample seeded payment';
    $pStmt = $conn->prepare("INSERT INTO payments (booking_id, payment_date, amount_paid, payment_method, payment_status, notes, created_at) VALUES (?,?,?,?,?,?,NOW())");
    $pStmt->bind_param('isdsss', $bookingId, $checkin, $total, $method, $payStatus, $notes);
    $pStmt->execute();
    $pStmt->close();
}

$conn->close();
echo "Seed complete: 10 users, 10 tenants, 50 bookings, 50 payments.\n";