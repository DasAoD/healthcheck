<?php
/**
 * public/index.php
 * Main dashboard – shows all sites and their check states.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$user = auth_require('/login.php');

// ── Data ──────────────────────────────────────────────────────────────────────

$sites = db_fetch_all(
    'SELECT * FROM sites WHERE active = 1 ORDER BY sort_order, name'
);

// Load all checks + their current state in one query, groups sorted by check_groups.sort_order
$rows = db_fetch_all(
    'SELECT c.*,
            s.name      AS site_name,
            st.status   AS state_status,
            st.since    AS state_since,
            st.last_check,
            st.detail   AS state_detail,
            st.fail_count
       FROM checks c
       JOIN sites  s  ON s.id  = c.site_id
  LEFT JOIN states st ON st.check_id = c.id
  LEFT JOIN check_groups cg ON cg.site_id = c.site_id AND cg.name = c.group_name
      WHERE c.active = 1
        AND s.active = 1
      ORDER BY s.sort_order,
               s.id,
               CASE WHEN c.group_name = \'\' THEN -1 ELSE COALESCE(cg.sort_order, 999) END,
               c.group_name,
               c.sort_order,
               c.id'
);

// Group checks by site_id → group_name
$bySite = [];
foreach ($rows as $row) {
    $siteId    = (int) $row['site_id'];
    $groupName = $row['group_name'] !== '' ? $row['group_name'] : '';
    $bySite[$siteId][$groupName][] = $row;
}

// Summary counters
$totalUp      = 0;
$totalDown    = 0;
$totalUnknown = 0;

foreach ($rows as $r) {
    match ($r['state_status'] ?? 'unknown') {
        'up'      => $totalUp++,
        'down'    => $totalDown++,
        default   => $totalUnknown++,
    };
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function status_badge(string $status): string
{
    $label = htmlspecialchars(strtoupper($status));
    return match ($status) {
        'up'    => "<span class=\"badge badge-up\">$label</span>",
        'down'  => "<span class=\"badge badge-down\">$label</span>",
        default => "<span class=\"badge badge-unknown\">$label</span>",
    };
}

function status_dot(string $status): string
{
    $cls = match ($status) {
        'up'    => 'dot-up',
        'down'  => 'dot-down',
        default => 'dot-unknown',
    };
    return "<span class=\"dot $cls\"></span>";
}

function relative_time(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '–';
    }

    $diff = time() - strtotime($datetime);

    if ($diff < 60)   return 'gerade eben';
    if ($diff < 3600) return 'vor ' . floor($diff / 60) . ' Min.';
    if ($diff < 86400) return 'vor ' . floor($diff / 3600) . ' Std.';

    return 'vor ' . floor($diff / 86400) . ' Tagen';
}

// ── Page ──────────────────────────────────────────────────────────────────────

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../templates/header.php';
?>

<?php if (empty($sites)): ?>
    <div class="card">
        <div class="card-body" style="padding:32px;text-align:center;color:var(--color-muted);">
            Noch keine Standorte konfiguriert.
        </div>
    </div>
<?php else: ?>

    <!-- Summary bar -->
    <div style="display:flex;gap:12px;margin-bottom:28px;flex-wrap:wrap;">
        <?php
        $summaryItems = [
            ['label' => 'Online',   'count' => $totalUp,      'color' => '#c6f6d5', 'text' => '#22543d'],
            ['label' => 'Offline',  'count' => $totalDown,    'color' => '#fed7d7', 'text' => '#742a2a'],
            ['label' => 'Unbekannt','count' => $totalUnknown, 'color' => '#e2e8f0', 'text' => '#4a5568'],
            ['label' => 'Gesamt',   'count' => count($rows),  'color' => '#ebf8ff', 'text' => '#2b6cb0'],
        ];
        foreach ($summaryItems as $item): ?>
            <div style="background:<?= $item['color'] ?>;color:<?= $item['text'] ?>;
                        padding:12px 20px;border-radius:6px;min-width:110px;text-align:center;">
                <div style="font-size:24px;font-weight:700;"><?= $item['count'] ?></div>
                <div style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.4px;">
                    <?= $item['label'] ?>
                </div>
            </div>
        <?php endforeach ?>
    </div>

    <?php foreach ($sites as $site):
        $siteId     = (int) $site['id'];
        $siteChecks = $bySite[$siteId] ?? [];

        // Count down checks per site for the site header indicator
        $siteDown = 0;
        foreach ($siteChecks as $group) {
            foreach ($group as $chk) {
                if (($chk['state_status'] ?? 'unknown') === 'down') $siteDown++;
            }
        }
    ?>
    <div class="card">
        <div class="card-header">
            <?= status_dot($siteDown > 0 ? 'down' : 'up') ?>
            <?= htmlspecialchars($site['name']) ?>
            <?php if ($site['short'] !== ''): ?>
                <span style="font-weight:400;color:var(--color-muted);font-size:13px;">
                    (<?= htmlspecialchars($site['short']) ?>)
                </span>
            <?php endif ?>
        </div>

        <div class="card-body">
            <?php if (empty($siteChecks)): ?>
                <p style="padding:20px;color:var(--color-muted);">Keine aktiven Checks.</p>
            <?php else:
                foreach ($siteChecks as $groupName => $checks): ?>

                <?php if ($groupName !== ''): ?>
                    <div style="padding:8px 20px 4px;font-size:11px;font-weight:700;
                                text-transform:uppercase;letter-spacing:.5px;color:var(--color-muted);
                                border-top:1px solid var(--color-border);">
                        <?= htmlspecialchars($groupName) ?>
                    </div>
                <?php endif ?>

                <table style="width:100%;border-collapse:collapse;">
                    <tbody>
                    <?php foreach ($checks as $chk):
                        $st     = $chk['state_status'] ?? 'unknown';
                        $detail = $chk['state_detail']  ?? '';
                    ?>
                    <tr style="border-top:1px solid var(--color-border);">
                        <!-- Status -->
                        <td style="width:80px;padding:10px 16px 10px 20px;">
                            <?= status_badge($st) ?>
                        </td>

                        <!-- Label + host -->
                        <td style="padding:10px 12px;">
                            <span style="font-weight:500;">
                                <?= htmlspecialchars($chk['label']) ?>
                            </span>
                            <span style="color:var(--color-muted);margin-left:8px;font-size:12px;">
                                <?= htmlspecialchars($chk['host']) ?>
                                <?php if ($chk['type'] === 'port' && $chk['port']): ?>
                                    :<?= (int) $chk['port'] ?>
                                <?php endif ?>
                            </span>
                        </td>

                        <!-- Type -->
                        <td style="width:60px;padding:10px 8px;color:var(--color-muted);font-size:12px;">
                            <?= htmlspecialchars(strtoupper($chk['type'])) ?>
                        </td>

                        <!-- Detail -->
                        <td style="padding:10px 8px;color:var(--color-muted);font-size:12px;max-width:260px;
                                   overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            <?= htmlspecialchars($detail) ?>
                        </td>

                        <!-- Last check -->
                        <td style="width:130px;padding:10px 20px 10px 8px;text-align:right;
                                   color:var(--color-muted);font-size:12px;white-space:nowrap;">
                            <?= relative_time($chk['last_check']) ?>
                        </td>
                    </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>

                <?php endforeach ?>
            <?php endif ?>
        </div>
    </div>
    <?php endforeach ?>

<?php endif ?>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
