<?php
/**
 * public/login.php
 * Login page.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Redirect if already logged in
if (auth_user() !== null) {
    header('Location: /index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $result = auth_login($username, $password);

    if ($result['ok']) {
        header('Location: /index.php');
        exit;
    }

    $error = $result['error'] ?? 'Anmeldung fehlgeschlagen.';
}

// Ensure default admin exists on first run
auth_ensure_default_admin();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anmelden – <?= htmlspecialchars(setting('site_title', 'Healthcheck')) ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --color-bg:      #f4f6f9;
            --color-surface: #ffffff;
            --color-border:  #e2e8f0;
            --color-text:    #1a202c;
            --color-muted:   #718096;
            --color-primary: #3b82f6;
            --radius:        6px;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--color-bg);
            color: var(--color-text);
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .login-box {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius);
            box-shadow: 0 4px 16px rgba(0,0,0,.08);
            padding: 40px 36px;
            width: 100%;
            max-width: 380px;
        }

        .login-box h1 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .login-box .subtitle {
            color: var(--color-muted);
            font-size: 13px;
            margin-bottom: 28px;
        }

        .form-group { margin-bottom: 16px; }

        label {
            display: block;
            font-weight: 500;
            margin-bottom: 5px;
            font-size: 13px;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid var(--color-border);
            border-radius: var(--radius);
            font-size: 14px;
            outline: none;
            transition: border-color .15s;
        }

        input:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(59,130,246,.15);
        }

        .btn-primary {
            width: 100%;
            padding: 10px;
            background: var(--color-primary);
            color: #fff;
            border: none;
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 8px;
            transition: opacity .15s;
        }

        .btn-primary:hover { opacity: .88; }

        .alert-error {
            background: #fff5f5;
            border: 1px solid #feb2b2;
            color: #c53030;
            padding: 10px 14px;
            border-radius: var(--radius);
            margin-bottom: 18px;
            font-size: 13px;
        }
    </style>
</head>
<body>

<div class="login-box">
    <h1><?= htmlspecialchars(setting('site_title', 'Healthcheck')) ?></h1>
    <p class="subtitle">Bitte melde dich an, um fortzufahren.</p>

    <?php if ($error !== ''): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif ?>

    <form method="post" action="/login.php" autocomplete="on">
        <div class="form-group">
            <label for="username">Benutzername</label>
            <input
                type="text"
                id="username"
                name="username"
                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                autocomplete="username"
                autofocus
                required
            >
        </div>

        <div class="form-group">
            <label for="password">Passwort</label>
            <input
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                required
            >
        </div>

        <button type="submit" class="btn-primary">Anmelden</button>
    </form>
</div>

</body>
</html>
