<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session_params.php';
session_start();

require_once __DIR__ . '/../core/SessionManager.php';

(new SessionManager())->destroy();

header('Location: ../index.php');
exit;