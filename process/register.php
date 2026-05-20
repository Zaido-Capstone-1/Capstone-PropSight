<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session_params.php';
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

const BLOCKED_DOMAINS = [
    'mailinator.com',
    'guerrillamail.com',
    'tempmail.com',
    'throwam.com',
    'sharklasers.com',
    'guerrillamailblock.com',
    'grr.la',
    'guerrillamail.info',
    'spam4.me',
    'trashmail.com',
    'trashmail.me',
    'trashmail.net',
    'dispostable.com',
    'yopmail.com',
    'yopmail.fr',
    'cool.fr.nf',
    'jetable.fr.nf',
    'nospam.ze.tc',
    'nomail.xl.cx',
    'mega.zik.dj',
    'speed.1s.fr',
    'courriel.fr.nf',
    'moncourrier.fr.nf',
    'monemail.fr.nf',
    'monmail.fr.nf',
    'fakeinbox.com',
    'mailnull.com',
    'spamgourmet.com',
    'spamgourmet.net',
    'spamgourmet.org',
    'maildrop.cc',
    'discard.email',
    'spamspot.com',
    'spamthisplease.com',
    'spamhereplease.com',
    'getnada.com',
    'filzmail.com',
    'tempr.email',
    'mailnesia.com',
    'owlpic.com',
];

const ALLOWED_EMAIL_DOMAINS = [
    'gmail.com',
    'googlemail.com',
    'phinmaed.com',
    'outlook.com',
    'hotmail.com',
    'live.com',
    'msn.com',
    'hotmail.co.uk',
    'hotmail.fr',
    'hotmail.de',
    'hotmail.it',
    'hotmail.es',
    'live.co.uk',
    'live.fr',
    'live.de',
    'live.it',
    'live.es',
    'live.com.au',
    'yahoo.com',
    'yahoo.co.uk',
    'yahoo.co.in',
    'yahoo.fr',
    'yahoo.de',
    'yahoo.it',
    'yahoo.es',
    'yahoo.com.au',
    'yahoo.com.ph',
    'icloud.com',
    'me.com',
    'mac.com',
    'proton.me',
    'protonmail.com',
    'protonmail.ch',
    'zoho.com',
    'zohomail.com',
    'aol.com',
    'aol.co.uk',
    'pldtmydsl.net',
    'globe.com.ph',
    'smart.com.ph',
    'mail.com',
    'email.com',
    'fastmail.com',
    'fastmail.fm',
    'tutanota.com',
    'tutamail.com',
    'tuta.io',
    'gmx.com',
    'gmx.net',
    'gmx.de',
    'hey.com',
    'pm.me',
];

function json_response(string $status, string $message): never
{
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

function json_error(string $message): never
{
    json_response('error', $message);
}

function json_success(string $message): never
{
    json_response('success', $message);
}

function domain_has_dns(string $domain): bool
{
    return checkdnsrr($domain, 'A') || checkdnsrr($domain, 'AAAA');
}

function get_email_domain(string $email): string
{
    return strtolower(substr(strrchr($email, '@'), 1));
}

if (
    empty($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])
) {
    json_error('Invalid CSRF token!');
}

if (!isset($_POST['register'])) {
    json_error('Invalid request!');
}

$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$phone = trim($_POST['phone'] ?? '');
$nationality = $_POST['nationality'] ?? null;
$birthday = $_POST['birthday'] ?? null;
$gender = $_POST['gender'] ?? null;
$password = $_POST['password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

if (
    $first_name === '' || $last_name === '' ||
    $email === '' || $phone === '' ||
    $password === '' || $confirm === ''
) {
    json_error('All fields are required!');
}


if (!preg_match('/^\+?[0-9]{7,15}$/', $phone)) {
    json_error('Invalid phone number!');
}


if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error('Invalid email!');
}

//Validation of email domain

$email_domain = get_email_domain($email);

if (in_array($email_domain, BLOCKED_DOMAINS, true)) {
    json_error('Disposable or temporary email addresses are not allowed!');
}

if (!in_array($email_domain, ALLOWED_EMAIL_DOMAINS, true)) {
    json_error('Please use a valid email provider (e.g. Gmail, Outlook, Yahoo)!');
}

if (!domain_has_dns($email_domain)) {
    json_error('Email domain does not appear to exist!');
}

if (!checkdnsrr($email_domain, 'MX')) {
    json_error('Email domain does not exist or cannot receive emails!');
}

//Password validation

if ($password !== $confirm) {
    json_error('Passwords do not match!');
}

if (strlen($password) < 8) {
    json_error('Password must be at least 6 characters!');
}

if (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
    json_error('Password must contain at least 1 uppercase letter and 1 number!');
}

//Checking of existing email or phone number

$check = $conn->prepare("SELECT user_id FROM users WHERE email = ? OR phone = ? LIMIT 1");
$check->bind_param('ss', $email, $phone);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    $check->close();
    json_error('Email or phone number already exists!');
}

$check->close();

//Inserting user

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("
    INSERT INTO users
        (first_name, last_name, email, phone, nationality, birthday, gender, password, verification_status)
    VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, 'Not Verified')
");
$stmt->bind_param('ssssssss', $first_name, $last_name, $email, $phone, $nationality, $birthday, $gender, $hashed_password);

if (!$stmt->execute()) {
    error_log('Registration DB error: ' . $stmt->error);
    $stmt->close();
    $conn->close();
    json_error('Something went wrong. Please try again.');
}

$new_user_id = $stmt->insert_id;
// Also insert default user_settings row for 2FA etc.
$settingsStmt = $conn->prepare("INSERT IGNORE INTO user_settings (user_id) VALUES (?)");
if ($settingsStmt) {
    $settingsStmt->bind_param('i', $new_user_id);
    $settingsStmt->execute();
    $settingsStmt->close();
}
$stmt->close();
$_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // refresh CSRF after register

// Auto-login with a restricted pending_verification session
session_regenerate_id(true);
$_SESSION['login'] = true;
$_SESSION['pending_verification'] = true;
$_SESSION['user_id'] = $new_user_id;
$_SESSION['first_name'] = $first_name;
$_SESSION['last_name'] = $last_name;
$_SESSION['name'] = $first_name . ' ' . $last_name;
$_SESSION['email'] = $email;
$_SESSION['phone'] = $phone;
$_SESSION['role'] = 'user';
$_SESSION['verification_status'] = 'Not Verified';

$conn->close();

// Return redirect — JS will send user to verify.php
echo json_encode([
    'status' => 'verify_required',
    'message' => 'Account created! Please verify your account.',
    'redirect' => 'verify.php',
]);
exit;