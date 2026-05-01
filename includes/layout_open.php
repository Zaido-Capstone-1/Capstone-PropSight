<?php
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>"/>
  <title><?= htmlspecialchars($page_title ?? 'PropSight') ?> — PropSight</title>
  <link rel="stylesheet" href="../../assets/css/admin-css/style.css"/>
  <link rel="icon" type="image/png" href="../../assets/images/final logo.png"/>
</head>
<body>

<script>
window.PS_CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
window.psGetCsrfToken = function () {
    if (window.PS_CSRF_TOKEN) return String(window.PS_CSRF_TOKEN);
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? String(meta.getAttribute('content') || '') : '';
};
window.psAppendCsrf = function (target) {
    const token = window.psGetCsrfToken();
    if (!token || !target || typeof target.append !== 'function') return target;
    target.append('csrf_token', token);
    return target;
};
</script>

<?php include 'sidebar.php'; ?>

<div class="main">

  <div class="content" style="margin-top: 25px;">
