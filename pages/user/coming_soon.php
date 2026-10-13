<?php
include '../../includes/session.php';
if ($_SESSION['role'] !== 'user') {
    echo '<!DOCTYPE html><html><head></head><body></body></html>';
    exit;
}

// Optional ?feature=Loyalty Redemption to customize the message.
// Falls back to a generic label if not provided.
$feature = trim($_GET['feature'] ?? '');
$feature = $feature !== '' ? htmlspecialchars($feature) : 'This page';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coming Soon — PropSight</title>
    <link rel="icon" type="image/png" href="../../assets/images/logo.png" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            padding: 24px;
        }

        .wrap {
            max-width: 380px;
            width: 100%;
            text-align: center;
        }

        .icon-wrap {
            width: 56px;
            height: 56px;
            margin: 0 auto 24px;
            border-radius: 14px;
            background: #0f2744;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .icon-wrap svg {
            width: 26px;
            height: 26px;
            stroke: #e8c86a;
        }

        h1 {
            font-size: 18px;
            font-weight: 700;
            color: #0f2744;
            margin-bottom: 8px;
        }

        p {
            font-size: 14px;
            color: #94a3b8;
            line-height: 1.6;
            margin-bottom: 28px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 600;
            color: #475569;
            padding: 10px 18px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            transition: background .15s, border-color .15s;
        }

        .btn:hover {
            background: #fff;
            border-color: #cbd5e1;
        }

        .btn svg {
            width: 15px;
            height: 15px;
        }
    </style>
</head>

<body>
    <div class="wrap">
        <!--<div class="icon-wrap">-->
        <!--    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">-->
        <!--        <circle cx="12" cy="12" r="10" />-->
        <!--        <path d="M12 8v4l2.5 2.5" />-->
        <!--    </svg>-->
        <!--</div>-->
        <h1><?= $feature ?> isn't available yet</h1>
        <p>We're still working on this one. Check back soon.</p>
        <a href="../../process/logout.php" class="btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                <polyline points="16 17 21 12 16 7" />
                <line x1="21" y1="12" x2="9" y2="12" />
            </svg>
            Log Out
        </a>
    </div>
</body>

</html>