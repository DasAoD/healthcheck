<?php
/**
 * includes/notifier.php
 * Alert dispatching: Telegram + SMTP e-mail with two-stage debounce / repeat logic.
 *
 * Debounce rules (all values from settings table):
 *  - First alert   : immediately when fail_count >= fail_threshold
 *  - Stage 1 repeat: down < alert_long_down_min  → repeat every alert_repeat_min
 *  - Stage 2 repeat: down >= alert_long_down_min → repeat every alert_repeat_extended_min
 *  - Flood guard   : never send two alerts within alert_debounce_min minutes
 *  - Recovery      : when status flips to 'up' and a previous alert was sent (last_alert IS NOT NULL)
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

// ── Public entry point ────────────────────────────────────────────────────────

/**
 * Evaluates whether a notification must be sent and dispatches it.
 * Call this after saving the updated state to the database.
 *
 * @param array<string, mixed> $check  Row from the checks table
 * @param array<string, mixed> $state  Current row from the states table
 */
function notify_if_needed(array $check, array $state): void
{
    $failThreshold       = (int) setting('fail_threshold',            '2');
    $debounceMin         = (int) setting('alert_debounce_min',        '5');
    $repeatMin           = (int) setting('alert_repeat_min',          '60');
    $longDownMin         = (int) setting('alert_long_down_min',       '360');
    $repeatExtendedMin   = (int) setting('alert_repeat_extended_min', '1440');
    $maxCount            = (int) setting('alert_max_count',           '5');

    $status      = (string) $state['status'];
    $failCount   = (int)    $state['fail_count'];
    $alertCount  = (int)    ($state['alert_count'] ?? 0);
    $lastAlert   = $state['last_alert']  ?? null;
    $lastChange  = $state['last_change'] ?? null;
    $since       = $state['since']       ?? null;

    $now            = time();
    $minsSinceAlert = $lastAlert !== null
        ? (int) round(($now - strtotime($lastAlert)) / 60)
        : PHP_INT_MAX;

    // ── Recovery ──────────────────────────────────────────────────────────────
    if ($status === 'up' && $lastAlert !== null) {
        // Only send recovery if the last_alert predates the last status change
        if ($lastChange !== null && strtotime($lastChange) > strtotime($lastAlert)) {
            dispatch_alert($check, $state, 'recovery');
            clear_last_alert((int) $check['id']); // also resets alert_count
            return;
        }
    }

    // ── Down alerts ───────────────────────────────────────────────────────────
    if ($status !== 'down') {
        return;
    }

    if ($failCount < $failThreshold) {
        return; // Not yet above threshold
    }

    // Max alert count guard (0 = unlimited)
    if ($maxCount > 0 && $alertCount >= $maxCount) {
        return;
    }

    // Flood guard: never alert more often than debounce_min
    if ($minsSinceAlert < $debounceMin) {
        return;
    }

    // First alert (no previous alert for this incident)
    if ($lastAlert === null || ($lastChange !== null && strtotime($lastChange) > strtotime($lastAlert))) {
        dispatch_alert($check, $state, 'down');
        update_last_alert((int) $check['id']);
        return;
    }

    // Two-stage repeat logic
    // minsDown = how long this check has been continuously down
    $minsDown = $since !== null
        ? (int) round(($now - strtotime($since)) / 60)
        : PHP_INT_MAX;

    // Stage 2: long-running outage → use extended repeat interval
    if ($minsDown >= $longDownMin) {
        if ($minsSinceAlert >= $repeatExtendedMin) {
            dispatch_alert($check, $state, 'repeat_extended');
            update_last_alert((int) $check['id']);
        }
        return;
    }

    // Stage 1: normal repeat interval
    if ($minsSinceAlert >= $repeatMin) {
        dispatch_alert($check, $state, 'repeat');
        update_last_alert((int) $check['id']);
    }
}

// ── Dispatch ──────────────────────────────────────────────────────────────────

/**
 * Sends the alert via all enabled channels.
 *
 * @param string $event  'down' | 'repeat' | 'recovery'
 */
function dispatch_alert(array $check, array $state, string $event): void
{
    $text    = build_alert_text($check, $state, $event);
    $subject = build_alert_subject($check, $event);

    if (setting('notify_telegram') === '1') {
        send_telegram($text);
    }

    if (setting('notify_mail') === '1') {
        send_mail($subject, $text);
    }
}

// ── Message builder ───────────────────────────────────────────────────────────

/**
 * Builds the plain-text alert message.
 */
function build_alert_text(array $check, array $state, string $event): string
{
    $icon = match ($event) {
        'down'            => '[DOWN]',
        'repeat'          => '[STILL DOWN]',
        'repeat_extended' => '[LONG DOWN]',
        'recovery'        => '[UP]',
        default           => '[ALERT]',
    };

    $since  = $state['last_change'] ?? $state['since'] ?? 'unknown';
    $detail = $state['detail'] !== '' ? $state['detail'] : 'no detail';

    $lines = [
        $icon . ' ' . $check['label'],
        'Host : ' . $check['host'],
        'Type : ' . strtoupper((string) $check['type']),
        'Since: ' . $since . ' UTC',
        'Info : ' . $detail,
    ];

    if ((string) $check['description'] !== '') {
        $lines[] = 'Note : ' . $check['description'];
    }

    return implode("\n", $lines);
}

/**
 * Builds the e-mail subject line.
 */
function build_alert_subject(array $check, string $event): string
{
    $prefix = match ($event) {
        'down'            => '[DOWN]',
        'repeat'          => '[STILL DOWN]',
        'repeat_extended' => '[LONG DOWN]',
        'recovery'        => '[RECOVERED]',
        default           => '[ALERT]',
    };

    return $prefix . ' ' . $check['label'] . ' (' . $check['host'] . ')';
}

// ── State helpers ─────────────────────────────────────────────────────────────

function update_last_alert(int $checkId): void
{
    db_execute(
        "UPDATE states SET last_alert = datetime('now'), alert_count = alert_count + 1 WHERE check_id = ?",
        [$checkId]
    );
}

function clear_last_alert(int $checkId): void
{
    db_execute(
        'UPDATE states SET last_alert = NULL, alert_count = 0 WHERE check_id = ?',
        [$checkId]
    );
}

// ── Telegram ──────────────────────────────────────────────────────────────────

/**
 * Sends a plain-text message via the Telegram Bot API.
 */
function send_telegram(string $message): bool
{
    $token  = setting('telegram_bot_token');
    $chatId = setting('telegram_chat_id');

    if ($token === '' || $chatId === '') {
        error_log('notifier: Telegram not configured (missing token or chat_id)');
        return false;
    }

    $url     = 'https://api.telegram.org/bot' . rawurlencode($token) . '/sendMessage';
    $payload = json_encode([
        'chat_id'    => $chatId,
        'text'       => $message,
        'parse_mode' => 'HTML',
    ]);

    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n"
                       . 'Content-Length: ' . strlen($payload) . "\r\n",
            'content' => $payload,
            'timeout' => 10,
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        error_log('notifier: Telegram request failed');
        return false;
    }

    $data = json_decode($response, true);

    if (!($data['ok'] ?? false)) {
        error_log('notifier: Telegram API error – ' . ($data['description'] ?? 'unknown'));
        return false;
    }

    return true;
}

// ── E-Mail (SMTP) ─────────────────────────────────────────────────────────────

/**
 * Sends a plain-text e-mail via SMTP (with optional STARTTLS + AUTH LOGIN).
 */
function send_mail(string $subject, string $body): bool
{
    $from = setting('mail_from');
    $to   = setting('mail_to');

    if ($from === '' || $to === '') {
        error_log('notifier: Mail not configured (missing mail_from or mail_to)');
        return false;
    }

    return smtp_send(
        from:    $from,
        to:      $to,
        subject: $subject,
        body:    $body,
    );
}

/**
 * Minimal SMTP client with STARTTLS and AUTH LOGIN support.
 *
 * @throws RuntimeException on connection / protocol errors
 */
function smtp_send(string $from, string $to, string $subject, string $body): bool
{
    $host = setting('mail_smtp_host');
    $port = (int) setting('mail_smtp_port', '587');
    $user = setting('mail_smtp_user');
    $pass = setting('mail_smtp_pass');

    if ($host === '') {
        error_log('notifier: SMTP host not configured');
        return false;
    }

    // ── Connect ───────────────────────────────────────────────────────────────
    $socket = @fsockopen('tcp://' . $host, $port, $errno, $errstr, 10);

    if ($socket === false) {
        error_log(sprintf('notifier: SMTP connect failed – %s (errno %d)', $errstr, $errno));
        return false;
    }

    stream_set_timeout($socket, 10);

    try {
        smtp_expect($socket, '220');                         // server greeting

        smtp_cmd($socket, 'EHLO ' . smtp_local_hostname());
        $caps = smtp_read_multiline($socket, '250');         // server capabilities

        // ── STARTTLS (port 587 / explicit TLS) ────────────────────────────────
        if ($port === 587 || in_array('STARTTLS', $caps, true)) {
            smtp_cmd($socket, 'STARTTLS');
            smtp_expect($socket, '220');

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS negotiation failed');
            }

            smtp_cmd($socket, 'EHLO ' . smtp_local_hostname());
            smtp_read_multiline($socket, '250');
        }

        // ── AUTH LOGIN ────────────────────────────────────────────────────────
        if ($user !== '' && $pass !== '') {
            smtp_cmd($socket, 'AUTH LOGIN');
            smtp_expect($socket, '334');
            smtp_cmd($socket, base64_encode($user));
            smtp_expect($socket, '334');
            smtp_cmd($socket, base64_encode($pass));
            smtp_expect($socket, '235');
        }

        // ── Envelope ─────────────────────────────────────────────────────────
        smtp_cmd($socket, 'MAIL FROM:<' . $from . '>');
        smtp_expect($socket, '250');

        smtp_cmd($socket, 'RCPT TO:<' . $to . '>');
        smtp_expect($socket, '250');

        // ── Message body ──────────────────────────────────────────────────────
        smtp_cmd($socket, 'DATA');
        smtp_expect($socket, '354');

        $date    = date('r');
        $headers = implode("\r\n", [
            'Date: '         . $date,
            'From: '         . $from,
            'To: '           . $to,
            'Subject: '      . $subject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: Healthcheck-Monitor/1.0',
        ]);

        // Dot-stuffing: lines starting with '.' must be doubled
        $safeBody = preg_replace('/^\./', '..', $body, flags: PREG_OFFSET_CAPTURE) ?? $body;
        $safeBody = str_replace("\n.", "\n..", $body);

        fwrite($socket, $headers . "\r\n\r\n" . $safeBody . "\r\n.\r\n");
        smtp_expect($socket, '250');

        smtp_cmd($socket, 'QUIT');

    } catch (RuntimeException $e) {
        error_log('notifier: SMTP error – ' . $e->getMessage());
        fclose($socket);
        return false;
    }

    fclose($socket);
    return true;
}

// ── SMTP helpers ──────────────────────────────────────────────────────────────

/** Writes a command to the SMTP socket. */
function smtp_cmd(mixed $socket, string $cmd): void
{
    fwrite($socket, $cmd . "\r\n");
}

/**
 * Reads a single-line response and asserts the expected status code.
 *
 * @throws RuntimeException on unexpected response
 */
function smtp_expect(mixed $socket, string $expected): string
{
    $line = fgets($socket, 512);

    if ($line === false) {
        throw new RuntimeException('SMTP connection lost');
    }

    $code = substr($line, 0, 3);

    if ($code !== $expected) {
        throw new RuntimeException(sprintf('Expected %s, got: %s', $expected, trim($line)));
    }

    return trim($line);
}

/**
 * Reads a multi-line SMTP response (e.g. EHLO capabilities).
 * Returns list of capability strings (without the 250- prefix).
 *
 * @return string[]
 */
function smtp_read_multiline(mixed $socket, string $expected): array
{
    $caps = [];

    while (true) {
        $line = fgets($socket, 512);

        if ($line === false) {
            throw new RuntimeException('SMTP connection lost while reading response');
        }

        $code      = substr($line, 0, 3);
        $separator = $line[3] ?? ' ';
        $content   = trim(substr($line, 4));

        if ($code !== $expected) {
            throw new RuntimeException(sprintf('Expected %s, got: %s', $expected, trim($line)));
        }

        $caps[] = strtoupper($content);

        if ($separator === ' ') {
            break; // last line of multi-line response
        }
    }

    return $caps;
}

/** Returns a safe local hostname for EHLO. */
function smtp_local_hostname(): string
{
    $host = gethostname();
    return ($host !== false && $host !== '') ? $host : 'localhost';
}
