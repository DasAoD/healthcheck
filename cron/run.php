<?php
/**
 * cron/run.php
 * Main check runner – execute via CLI only.
 *
 * Suggested crontab entry (every minute):
 *   * * * * * php /var/www/healthcheck/cron/run.php >> /var/www/healthcheck/logs/cron.log 2>&1
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/checker.php';
require_once __DIR__ . '/../includes/notifier.php';

$startedAt = microtime(true);

// ── Fetch all active checks ───────────────────────────────────────────────────

$checks = db_fetch_all(
    'SELECT c.*, s.name AS site_name
       FROM checks c
       JOIN sites  s ON s.id = c.site_id
      WHERE c.active = 1
        AND s.active = 1
      ORDER BY s.sort_order, c.sort_order, c.id'
);

if (empty($checks)) {
    cron_log('No active checks found. Exiting.');
    exit(0);
}

cron_log(sprintf('Running %d check(s) …', count($checks)));

// ── Run each check ────────────────────────────────────────────────────────────

foreach ($checks as $check) {
    $result    = run_check($check);
    $now       = gmdate('Y-m-d H:i:s');
    $checkId   = (int) $check['id'];

    // Load previous state (needed for fail_count + last_alert preservation)
    $prevState = db_fetch_one(
        'SELECT * FROM states WHERE check_id = ?',
        [$checkId]
    );

    $prevStatus = $prevState['status'] ?? 'unknown';
    $newStatus  = $result['status'];

    // Consecutive failure counter
    $failCount = ($newStatus === 'down')
        ? (int) ($prevState['fail_count'] ?? 0) + 1
        : 0;

    // UPSERT – since / last_change only update when status actually changes
    db_execute(
        "INSERT INTO states
                (check_id, status, since, last_check, last_change, detail, fail_count, last_alert)
         VALUES (:check_id, :status, :now, :now, :now, :detail, :fail_count, NULL)
         ON CONFLICT(check_id) DO UPDATE SET
             status      = excluded.status,
             since       = CASE WHEN excluded.status != states.status
                                THEN excluded.last_check
                                ELSE states.since       END,
             last_check  = excluded.last_check,
             last_change = CASE WHEN excluded.status != states.status
                                THEN excluded.last_check
                                ELSE states.last_change END,
             detail      = excluded.detail,
             fail_count  = excluded.fail_count,
             last_alert  = states.last_alert",
        [
            ':check_id'  => $checkId,
            ':status'    => $newStatus,
            ':now'       => $now,
            ':detail'    => $result['detail'],
            ':fail_count' => $failCount,
        ]
    );

    // Append to check_log
    db_execute(
        'INSERT INTO check_log (check_id, checked_at, status, detail)
         VALUES (?, ?, ?, ?)',
        [$checkId, $now, $newStatus, $result['detail']]
    );

    // Reload state with updated values for the notifier
    $newState = db_fetch_one(
        'SELECT * FROM states WHERE check_id = ?',
        [$checkId]
    );

    notify_if_needed($check, $newState);

    cron_log(sprintf(
        '  %-30s %-6s %-7s %s',
        substr($check['label'], 0, 30),
        strtoupper($check['type']),
        strtoupper($newStatus),
        $result['detail']
    ));
}

// ── Cleanup old log entries ───────────────────────────────────────────────────

$retentionDays = (int) setting('log_retention_days', '30');

$deleted = db_execute(
    "DELETE FROM check_log
      WHERE checked_at < datetime('now', '-' || ? || ' days')",
    [$retentionDays]
);

if ($deleted > 0) {
    cron_log(sprintf('Cleanup: %d log entries older than %d days removed.', $deleted, $retentionDays));
}

$elapsed = round((microtime(true) - $startedAt) * 1000);
cron_log(sprintf('Done in %d ms.', $elapsed));

// ── Helper ────────────────────────────────────────────────────────────────────

function cron_log(string $message): void
{
    echo '[' . gmdate('Y-m-d H:i:s') . ' UTC] ' . $message . "\n";
}
