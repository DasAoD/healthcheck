<?php
/**
 * templates/header.php
 * Shared HTML head + top navigation.
 *
 * Expected variables (set before including):
 *   $pageTitle  string   – page-specific title suffix (optional)
 *   $user       array    – current authenticated user (optional)
 */

declare(strict_types=1);

$siteTitle  = setting('site_title', 'Healthcheck Dashboard');
$fullTitle  = isset($pageTitle) ? htmlspecialchars($pageTitle) . ' – ' . htmlspecialchars($siteTitle)
                                : htmlspecialchars($siteTitle);
$currentUser = $user ?? null;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <script>/* Apply stored theme before CSS renders to avoid flash */
        (function () {
            var t = localStorage.getItem('theme') || 'system';
            var el = document.documentElement;
            if (t === 'dark')  el.setAttribute('data-theme', 'dark');
            else if (t === 'light') el.setAttribute('data-theme', 'light');
        }());
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $fullTitle ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --color-bg:        #f4f6f9;
            --color-surface:   #ffffff;
            --color-border:    #e2e8f0;
            --color-text:      #1a202c;
            --color-muted:     #718096;
            --color-up:        #38a169;
            --color-down:      #e53e3e;
            --color-unknown:   #a0aec0;
            --color-primary:   #3b82f6;
            --radius:          6px;
            --shadow:          0 1px 3px rgba(0,0,0,.08);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--color-bg);
            color: var(--color-text);
            font-size: 14px;
            line-height: 1.5;
        }

        /* ── Navigation ──────────────────────────────────────────── */
        nav {
            background: var(--color-surface);
            border-bottom: 1px solid var(--color-border);
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 52px;
            box-shadow: var(--shadow);
        }

        nav .brand {
            font-weight: 700;
            font-size: 16px;
            color: var(--color-text);
            text-decoration: none;
        }

        nav .nav-right {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 13px;
            color: var(--color-muted);
        }

        nav .nav-right a {
            color: var(--color-primary);
            text-decoration: none;
        }

        nav .nav-right a:hover { text-decoration: underline; }

        /* ── Main layout ─────────────────────────────────────────── */
        main { max-width: 1100px; margin: 32px auto; padding: 0 24px; }

        /* ── Cards ───────────────────────────────────────────────── */
        .card {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 24px;
        }

        .card-header {
            padding: 14px 20px;
            border-bottom: 1px solid var(--color-border);
            font-weight: 600;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-body { padding: 0; }

        /* ── Status badge ────────────────────────────────────────── */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .badge-up      { background: #c6f6d5; color: #22543d; }
        .badge-down    { background: #fed7d7; color: #742a2a; }
        .badge-unknown { background: #e2e8f0; color: #4a5568; }

        /* ── Status dot ──────────────────────────────────────────── */
        .dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .dot-up      { background: var(--color-up); }
        .dot-down    { background: var(--color-down); box-shadow: 0 0 0 3px #fed7d7; }
        .dot-unknown { background: var(--color-unknown); }

        /* ── Alerts ──────────────────────────────────────────────── */
        .alert {
            padding: 10px 16px;
            border-radius: var(--radius);
            margin-bottom: 16px;
            font-size: 13px;
        }

        .alert-error   { background: #fff5f5; border: 1px solid #feb2b2; color: #c53030; }
        .alert-success { background: #f0fff4; border: 1px solid #9ae6b4; color: #276749; }

        /* ── Form elements ───────────────────────────────────────── */
        .form-group { margin-bottom: 16px; }

        label {
            display: block;
            font-weight: 500;
            margin-bottom: 4px;
            font-size: 13px;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--color-border);
            border-radius: var(--radius);
            font-size: 14px;
            outline: none;
            transition: border-color .15s;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(59,130,246,.15);
        }

        .btn {
            display: inline-block;
            padding: 8px 20px;
            border: none;
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: opacity .15s;
        }

        .btn:hover { opacity: .85; }
        .btn-primary { background: var(--color-primary); color: #fff; }
        .btn-sm { padding: 4px 12px; font-size: 12px; }
        .btn-danger { background: #fed7d7; color: #742a2a; }

        [data-theme="dark"] .btn-danger { background: #7f1d1d; color: #fca5a5; }
        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) .btn-danger { background: #7f1d1d; color: #fca5a5; }
        }

        .row-self { background: #fffbeb; }
        [data-theme="dark"] .row-self { background: #1c1a11; }
        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) .row-self { background: #1c1a11; }
        }

        /* ── Dark mode tokens (explicit choice) ──────────────────── */
        [data-theme="dark"] {
            --color-bg:      #111827;
            --color-surface: #1f2937;
            --color-border:  #374151;
            --color-text:    #f9fafb;
            --color-muted:   #9ca3af;
            --color-up:      #34d399;
            --color-down:    #f87171;
            --color-unknown: #6b7280;
            --color-primary: #60a5fa;
            --shadow:        0 1px 3px rgba(0,0,0,.4);
        }

        /* ── Dark mode tokens (system preference) ────────────────── */
        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) {
                --color-bg:      #111827;
                --color-surface: #1f2937;
                --color-border:  #374151;
                --color-text:    #f9fafb;
                --color-muted:   #9ca3af;
                --color-up:      #34d399;
                --color-down:    #f87171;
                --color-unknown: #6b7280;
                --color-primary: #60a5fa;
                --shadow:        0 1px 3px rgba(0,0,0,.4);
            }
        }

        /* ── Dark: badges ────────────────────────────────────────── */
        [data-theme="dark"] .badge-up      { background: #064e3b; color: #6ee7b7; }
        [data-theme="dark"] .badge-down    { background: #7f1d1d; color: #fca5a5; }
        [data-theme="dark"] .badge-unknown { background: #374151; color: #d1d5db; }
        [data-theme="dark"] .dot-down      { box-shadow: 0 0 0 3px rgba(239,68,68,.25); }

        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) .badge-up      { background: #064e3b; color: #6ee7b7; }
            :root:not([data-theme="light"]) .badge-down    { background: #7f1d1d; color: #fca5a5; }
            :root:not([data-theme="light"]) .badge-unknown { background: #374151; color: #d1d5db; }
            :root:not([data-theme="light"]) .dot-down      { box-shadow: 0 0 0 3px rgba(239,68,68,.25); }
        }

        /* ── Dark: alerts ────────────────────────────────────────── */
        [data-theme="dark"] .alert-error   { background: #450a0a; border-color: #dc2626; color: #fca5a5; }
        [data-theme="dark"] .alert-success { background: #052e16; border-color: #16a34a; color: #86efac; }

        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) .alert-error   { background: #450a0a; border-color: #dc2626; color: #fca5a5; }
            :root:not([data-theme="light"]) .alert-success { background: #052e16; border-color: #16a34a; color: #86efac; }
        }

        /* ── Dark: form inputs ───────────────────────────────────── */
        [data-theme="dark"] input,
        [data-theme="dark"] select,
        [data-theme="dark"] textarea { background: var(--color-surface); color: var(--color-text); }

        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) input,
            :root:not([data-theme="light"]) select,
            :root:not([data-theme="light"]) textarea { background: var(--color-surface); color: var(--color-text); }
        }

        /* ── Theme switcher ──────────────────────────────────────── */
        .theme-switcher {
            display: flex;
            border: 1px solid var(--color-border);
            border-radius: var(--radius);
            overflow: hidden;
        }

        .theme-switcher button {
            background: transparent;
            border: none;
            border-right: 1px solid var(--color-border);
            padding: 3px 10px;
            font-size: 12px;
            cursor: pointer;
            color: var(--color-muted);
            transition: background .15s, color .15s;
            line-height: 1.4;
        }

        .theme-switcher button:last-child { border-right: none; }

        .theme-switcher button:hover {
            background: var(--color-bg);
            color: var(--color-text);
        }

        .theme-switcher button.active {
            background: var(--color-primary);
            color: #fff;
        }
    </style>
</head>
<body>

<nav>
    <a href="/index.php" class="brand"><?= htmlspecialchars($siteTitle) ?></a>
    <div class="nav-right">
        <div class="theme-switcher" id="themeSwitcher" aria-label="Farbschema wählen">
            <button data-theme-set="light">Hell</button>
            <button data-theme-set="system">System</button>
            <button data-theme-set="dark">Dunkel</button>
        </div>
        <?php if ($currentUser): ?>
            <?php if ($currentUser['role'] === 'admin'): ?>
                <a href="/admin/">Admin</a>
            <?php endif ?>
            <a href="/admin/users.php?edit=<?= (int) $currentUser['id'] ?>">
                <?= htmlspecialchars($currentUser['username']) ?>
            </a>
            <a href="/logout.php">Abmelden</a>
        <?php endif ?>
    </div>
</nav>

<script>
(function () {
    var current = localStorage.getItem('theme') || 'system';
    var btns    = document.querySelectorAll('#themeSwitcher [data-theme-set]');

    function apply(mode) {
        var el = document.documentElement;
        if (mode === 'dark')       el.setAttribute('data-theme', 'dark');
        else if (mode === 'light') el.setAttribute('data-theme', 'light');
        else                       el.removeAttribute('data-theme');

        localStorage.setItem('theme', mode);

        btns.forEach(function (b) {
            b.classList.toggle('active', b.getAttribute('data-theme-set') === mode);
        });
    }

    btns.forEach(function (b) {
        b.addEventListener('click', function () { apply(b.getAttribute('data-theme-set')); });
    });

    apply(current);
}());
</script>

<main>
