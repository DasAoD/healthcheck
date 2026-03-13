<?php
/**
 * public/admin/settings.php
 * Application settings (key-value store).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/env.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

$user = auth_require_admin('/login.php');

// ── Action ────────────────────────────────────────────────────────────────────

$msg = (string) ($_GET['msg'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $allowed = [
        'site_title', 'site_url',
        'notify_telegram', 'telegram_bot_token', 'telegram_chat_id',
        'notify_mail', 'mail_from', 'mail_to',
        'mail_smtp_host', 'mail_smtp_port', 'mail_smtp_user', 'mail_smtp_pass',
        'fail_threshold', 'check_timeout_sec',
        'alert_debounce_min', 'alert_repeat_min',
        'alert_long_down_min', 'alert_repeat_extended_min',
        'alert_max_count', 'log_retention_days',
    ];

    foreach ($allowed as $key) {
        $value = (string) ($_POST[$key] ?? '');

        // Checkboxes send nothing when unchecked
        if (in_array($key, ['notify_telegram', 'notify_mail'], true)) {
            $value = isset($_POST[$key]) ? '1' : '0';
        }

        setting_set($key, $value);
    }

    header('Location: /admin/settings.php?msg=saved');
    exit;
}

// ── View ──────────────────────────────────────────────────────────────────────

$pageTitle = 'Einstellungen';
$adminPage = 'settings';
require_once __DIR__ . '/../../templates/header.php';
require_once __DIR__ . '/../../templates/admin_nav.php';

/** Renders a text input bound to a settings key. */
function settings_input(string $key, string $type = 'text', string $placeholder = ''): void
{
    $val = setting($key);
    echo '<input type="' . $type . '" name="' . htmlspecialchars($key) . '"'
       . ' value="' . ($type === 'password' ? '' : htmlspecialchars($val)) . '"'
       . ($placeholder ? ' placeholder="' . htmlspecialchars($placeholder) . '"' : '')
       . ($type === 'password' && $val !== '' ? ' placeholder="(gesetzt – leer lassen zum Beibehalten)"' : '')
       . ' autocomplete="off">';
}

/** Renders a number input bound to a settings key. */
function settings_number(string $key, int $min = 0, int $max = 99999): void
{
    $val = (int) setting($key);
    echo '<input type="number" name="' . htmlspecialchars($key) . '"'
       . ' value="' . $val . '" min="' . $min . '" max="' . $max . '">';
}

/** Renders a checkbox bound to a settings key. */
function settings_checkbox(string $key, string $label): void
{
    $checked = setting($key) === '1' ? 'checked' : '';
    echo '<label style="display:flex;align-items:center;gap:8px;cursor:pointer;">'
       . '<input type="checkbox" name="' . htmlspecialchars($key) . '" value="1" ' . $checked . '>'
       . htmlspecialchars($label) . '</label>';
}
?>

<?php if ($msg === 'saved'): ?>
    <div class="alert alert-success">Einstellungen gespeichert.</div>
<?php endif ?>

<form method="post" action="/admin/settings.php">
<?= csrf_field() ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;">

    <!-- General -->
    <div class="card">
        <div class="card-header">Allgemein</div>
        <div class="card-body" style="padding:20px;">

            <div class="form-group">
                <label>Seitentitel</label>
                <?php settings_input('site_title') ?>
            </div>

            <div class="form-group">
                <label>Basis-URL <span style="font-weight:400;color:var(--color-muted)">(ohne /)</span></label>
                <?php settings_input('site_url', 'text', 'https://healthcheck.example.com') ?>
            </div>
        </div>
    </div>

    <!-- Thresholds -->
    <div class="card">
        <div class="card-header">Schwellenwerte &amp; Timeouts</div>
        <div class="card-body" style="padding:20px;">

            <div class="form-group">
                <label>Fehler in Folge vor Alarm <code>fail_threshold</code></label>
                <?php settings_number('fail_threshold', 1, 20) ?>
            </div>

            <div class="form-group">
                <label>Check-Timeout (Sekunden)</label>
                <?php settings_number('check_timeout_sec', 1, 60) ?>
            </div>

            <div class="form-group">
                <label>Mindestabstand zwischen Alarmen (Min.) <code>debounce</code></label>
                <?php settings_number('alert_debounce_min', 1, 1440) ?>
            </div>

            <div style="border-top:1px solid var(--color-border);margin:16px 0 16px;
                        padding-top:16px;font-size:12px;font-weight:700;text-transform:uppercase;
                        letter-spacing:.4px;color:var(--color-muted);">
                Stufe 1 – Normaler Ausfall
            </div>

            <div class="form-group">
                <label>Wiederholungsalarm Stufe&nbsp;1 (Min.)</label>
                <?php settings_number('alert_repeat_min', 1, 10080) ?>
            </div>

            <div style="border-top:1px solid var(--color-border);margin:16px 0 16px;
                        padding-top:16px;font-size:12px;font-weight:700;text-transform:uppercase;
                        letter-spacing:.4px;color:var(--color-muted);">
                Stufe 2 – Längerer Ausfall
            </div>

            <div class="form-group">
                <label>Wechsel zu Stufe&nbsp;2 nach (Min.) <code>long_down</code></label>
                <?php settings_number('alert_long_down_min', 1, 10080) ?>
            </div>

            <div class="form-group">
                <label>Wiederholungsalarm Stufe&nbsp;2 (Min.)</label>
                <?php settings_number('alert_repeat_extended_min', 1, 10080) ?>
            </div>

            <div class="form-group">
                <label>Max. Alarme pro Ausfall <code>alert_max_count</code> <span style="font-weight:400;color:var(--color-muted)">(0 = unbegrenzt)</span></label>
                <?php settings_number('alert_max_count', 0, 100) ?>
            </div>

            <div style="border-top:1px solid var(--color-border);margin:16px 0 16px;
                        padding-top:16px;font-size:12px;font-weight:700;text-transform:uppercase;
                        letter-spacing:.4px;color:var(--color-muted);">
                Allgemein
            </div>

            <div class="form-group">
                <label>Log-Aufbewahrung (Tage)</label>
                <?php settings_number('log_retention_days', 1, 365) ?>
            </div>
        </div>
    </div>

    <!-- Telegram -->
    <div class="card">
        <div class="card-header">Telegram</div>
        <div class="card-body" style="padding:20px;">

            <div class="form-group">
                <?php settings_checkbox('notify_telegram', 'Telegram-Benachrichtigungen aktivieren') ?>
            </div>

            <div class="form-group">
                <label>Bot-Token</label>
                <?php settings_input('telegram_bot_token', 'password') ?>
            </div>

            <div class="form-group">
                <label>Chat-ID</label>
                <?php settings_input('telegram_chat_id', 'text', '123456789') ?>
            </div>
        </div>
    </div>

    <!-- E-Mail -->
    <div class="card">
        <div class="card-header">E-Mail (SMTP)</div>
        <div class="card-body" style="padding:20px;">

            <div class="form-group">
                <?php settings_checkbox('notify_mail', 'E-Mail-Benachrichtigungen aktivieren') ?>
            </div>

            <div class="form-group">
                <label>Absender (From)</label>
                <?php settings_input('mail_from', 'email', 'healthcheck@example.com') ?>
            </div>

            <div class="form-group">
                <label>Empfänger (To)</label>
                <?php settings_input('mail_to', 'email', 'admin@example.com') ?>
            </div>

            <div class="form-group">
                <label>SMTP-Host</label>
                <?php settings_input('mail_smtp_host', 'text', 'smtp.example.com') ?>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label>Port</label>
                    <?php settings_number('mail_smtp_port', 1, 65535) ?>
                </div>
            </div>

            <div class="form-group">
                <label>SMTP-Benutzername</label>
                <?php settings_input('mail_smtp_user') ?>
            </div>

            <div class="form-group">
                <label>SMTP-Passwort</label>
                <?php settings_input('mail_smtp_pass', 'password') ?>
            </div>
        </div>
    </div>

</div>

<div style="margin-top:8px;">
    <button type="submit" class="btn btn-primary">Einstellungen speichern</button>
</div>

</form>

<style>
    input[type="text"], input[type="email"],
    input[type="password"], input[type="number"] {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid var(--color-border);
        border-radius: var(--radius);
        font-size: 14px;
    }

    input[type="text"]:focus, input[type="email"]:focus,
    input[type="password"]:focus, input[type="number"]:focus {
        outline: none;
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px rgba(59,130,246,.15);
    }
</style>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
