<?php
require_once __DIR__ . '/../../includes/session_params.php';
session_start();
require_once '../../includes/db.php';

function redirect_with_toast($message, $type = 'error') {
    if ($type === 'success') {
        $_SESSION['toast_success'] = $message;
    } else {
        $_SESSION['toast_error'] = $message;
    }
    header("Location: ../../pages/user/profile.php");
    exit;
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    redirect_with_toast("Invalid CSRF token.");
}

if (!isset($_SESSION['user_id'])) {
    redirect_with_toast("You must be logged in to edit your profile.");
}

if (isset($_POST['edit_profile'])) {

    $user_id     = $_SESSION['user_id'];
    $first_name  = trim($_POST['first_name']);
    $last_name   = trim($_POST['last_name']);
    $email       = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $phone       = trim($_POST['phone']);
    $nationality = trim($_POST['nationality']);
    $birthday    = trim($_POST['birthday']);
    $gender      = trim($_POST['gender']);

    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone)) {
        redirect_with_toast("First name, last name, email, and phone are required.");
    }

    if ($phone && !preg_match("/^\+?[0-9]{7,15}$/", $phone)) {
        redirect_with_toast("Invalid phone number.");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirect_with_toast("Invalid email.");
    }

    $check = $conn->prepare("SELECT user_id FROM users WHERE (email = ? OR phone = ?) AND user_id != ?");
    $check->bind_param("ssi", $email, $phone, $user_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $check->close();
        $conn->close();
        redirect_with_toast("Email or phone number is already in use by another account.");
    }

    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, nationality = ?, birthday = ?, gender = ? WHERE user_id = ?");
    $stmt->bind_param("sssssssi", $first_name, $last_name, $email, $phone, $nationality, $birthday, $gender, $user_id);

    if ($stmt->execute()) {
        $_SESSION['first_name'] = $first_name;
        $_SESSION['last_name']  = $last_name;
        $_SESSION['email']      = $email;
        $_SESSION['phone']      = $phone;
        $_SESSION['nationality'] = $nationality;
        $_SESSION['birthday']    = $birthday;
        $_SESSION['gender']      = $gender;
        $stmt->close();
        $check->close();
        $conn->close();
        redirect_with_toast("Profile updated successfully!", "success");
    } else {
        error_log($stmt->error);
        $stmt->close();
        $check->close();
        $conn->close();
        redirect_with_toast("Something went wrong. Try again.");
    }

} else {
    redirect_with_toast("Invalid request.");
}
?>