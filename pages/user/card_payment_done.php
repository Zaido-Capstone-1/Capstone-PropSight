<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment
        <?= $_GET['status'] === 'cancelled' ? 'Cancelled' : 'Complete' ?> — PropSight
    </title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f8fafd;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            color: #1e3a5f;
        }

        .card {
            background: #fff;
            border-radius: 16px;
            padding: 48px 40px;
            text-align: center;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
            max-width: 420px;
            width: 90%;
        }

        .icon {
            font-size: 56px;
            margin-bottom: 20px;
        }

        h2 {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        p {
            font-size: 14px;
            color: #64748b;
            line-height: 1.6;
        }

        .close-btn {
            margin-top: 28px;
            display: inline-block;
            padding: 12px 32px;
            background: #1e3a5f;
            color: #fff;
            border: none;
            border-radius: 40px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }

        .close-btn:hover {
            background: #16304f;
        }
    </style>
</head>

<body>
    <?php $cancelled = ($_GET['status'] ?? '') === 'cancelled'; ?>
    <div class="card">
        <div class="icon">
            <?= $cancelled ? '❌' : '✅' ?>
        </div>
        <h2>
            <?= $cancelled ? 'Payment Cancelled' : 'Payment Received!' ?>
        </h2>
        <p>
            <?php if ($cancelled): ?>
                Your payment was cancelled. You can close this tab and try again from your booking page.
            <?php else: ?>
                Your card payment was successful. You can now close this tab — your booking page will update automatically.
            <?php endif; ?>
        </p>
        <button class="close-btn" onclick="window.close()">Close This Tab</button>
    </div>
    <script>
    // Auto-close after 5 seconds on success
    <?php if (!$cancelled): ?>
                setTimeout(() => window.close(), 5000);
    <?php endif; ?>
    </script>
</body>

</html>