<?php
// TEMPORARY — delete this file after debugging. Do not leave it deployed.
header('Content-Type: text/plain');

echo "=== getenv() ===\n";
foreach (['DB_SERVER', 'DB_USERNAME', 'DB_PASSWORD', 'DB_NAME', 'DB_PORT'] as $k) {
    $v = getenv($k);
    echo "$k = " . ($v === false ? '(not set)' : ($k === 'DB_PASSWORD' ? '(set, hidden)' : $v)) . "\n";
}

echo "\n=== \$_ENV ===\n";
foreach (['DB_SERVER', 'DB_USERNAME', 'DB_PASSWORD', 'DB_NAME', 'DB_PORT'] as $k) {
    echo "$k = " . (array_key_exists($k, $_ENV) ? ($k === 'DB_PASSWORD' ? '(set, hidden)' : $_ENV[$k]) : '(not set)') . "\n";
}

echo "\n=== \$_SERVER (relevant only) ===\n";
foreach (['DB_SERVER', 'DB_USERNAME', 'DB_PASSWORD', 'DB_NAME', 'DB_PORT'] as $k) {
    echo "$k = " . (array_key_exists($k, $_SERVER) ? ($k === 'DB_PASSWORD' ? '(set, hidden)' : $_SERVER[$k]) : '(not set)') . "\n";
}

echo "\n=== defined constants (from config.php, if reachable) ===\n";
try {
    require_once __DIR__ . '/config.php';
    echo "DB_SERVER = " . (defined('DB_SERVER') ? var_export(DB_SERVER, true) : '(constant not defined)') . "\n";
    echo "DB_NAME   = " . (defined('DB_NAME') ? var_export(DB_NAME, true) : '(constant not defined)') . "\n";
    echo "DB_PORT   = " . (defined('DB_PORT') ? var_export(DB_PORT, true) : '(constant not defined)') . "\n";
} catch (\Throwable $e) {
    echo "config.php threw: " . $e->getMessage() . "\n";
}