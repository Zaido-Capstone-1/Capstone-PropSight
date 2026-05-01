<?php
require_once __DIR__ . '/../includes/session_params.php';
session_start();
session_unset();
session_destroy();

header("Location: ../index.php");
exit;
?>