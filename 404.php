<?php
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found — PropSight</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Jost:wght@300;400;500;600&family=Playfair+Display:wght@400;600&display=swap"
        rel="stylesheet">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --navy-950: #060e1a;
            --navy-900: #0b1829;
            --navy-800: #112240;
            --navy-700: #1a3a5c;
            --navy-400: #3b82d4;
            --navy-200: #bfdbf7;
            --gold: #c9a84c;
            --cream: #f5f0e8;
            --text-soft: #8a9ab5;
        }

        body {
            font-family: 'Jost', sans-serif;
            background: var(--navy-950);
            color: var(--cream);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .container {
            text-align: center;
            max-width: 480px;
        }

        .code {
            font-family: 'Playfair Display', serif;
            font-size: clamp(6rem, 20vw, 9rem);
            font-weight: 400;
            color: var(--navy-800);
            line-height: 1;
            letter-spacing: -4px;
            margin-bottom: 0.5rem;
            position: relative;
        }

        .code::after {
            content: '404';
            position: absolute;
            inset: 0;
            color: transparent;
            -webkit-text-stroke: 1px var(--navy-700);
            letter-spacing: -4px;
        }

        .divider {
            width: 48px;
            height: 1px;
            background: var(--gold);
            margin: 1.5rem auto;
            opacity: 0.6;
        }

        h1 {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 400;
            color: var(--cream);
            margin-bottom: 0.75rem;
            letter-spacing: 0.02em;
        }

        p {
            font-size: 0.9rem;
            color: var(--text-soft);
            line-height: 1.7;
            margin-bottom: 2rem;
        }

        .actions {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 0.6rem 1.4rem;
            border-radius: 4px;
            font-family: 'Jost', sans-serif;
            font-size: 0.85rem;
            font-weight: 500;
            letter-spacing: 0.04em;
            text-decoration: none;
            transition: opacity 0.15s;
        }

        .btn:hover {
            opacity: 0.8;
        }

        .btn-primary {
            background: var(--gold);
            color: var(--navy-950);
        }

        .btn-secondary {
            background: transparent;
            color: var(--cream);
            border: 1px solid var(--navy-700);
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="code">404</div>
        <div class="divider"></div>
        <h1>Page not found</h1>
        <p>The page you're looking for doesn't exist or has been moved.<br>
            Let's get you back somewhere useful.</p>
        <div class="actions">
            <a href="/index.php" class="btn btn-primary">Go to login</a>
            <a href="javascript:history.back()" class="btn btn-secondary">Go back</a>
        </div>
    </div>
</body>

</html>