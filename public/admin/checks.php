<?php
/**
 * public/admin/checks.php
 * CRUD for checks.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/env.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

$user = auth_require_admin('/login.php');

// ── Actions ───────────────────────────────────────────────────────────────────

$msg = (string) ($_GET['msg'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add' || $action === 'edit') {
        $siteId      = (int)    ($_POST['site_id']    ?? 0);
        $label       = trim((string) ($_POST['label']       ?? ''));
        $host        = trim((string) ($_POST['host']        ?? ''));
        $type        = (string) ($_POST['type']       ?? 'ping');
        $port        = $_POST['port'] !== '' ? (int) $_POST['port'] : null;
        $groupName   = trim((string) ($_POST['group_name']  ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $sortOrder   = (int)    ($_POST['sort_order'] ?? 0);
        $active      = isset($_POST['active']) ? 1 : 0;

        if ($siteId === 0 || $label === '' || $host === '') {
            header('Location: /admin/checks.php?msg=invalid');
            exit;
        }

        if ($type === 'port' && ($port === null || $port < 1 || $port > 65535)) {
            header('Location: /admin/checks.php?msg=invalid_port');
            exit;
        }

        $params = [$siteId, $label, $host, $type, $port, $groupName, $description, $sortOrder, $active];

        if ($action === 'add') {
            db_execute(
                'INSERT INTO checks
                    (site_id, label, host, type, port, group_name, description, sort_order, active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                $params
            );
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            db_execute(
                'UPDATE checks
                    SET site_id = ?, label = ?, host = ?, type = ?, port = ?,
                        group_name = ?, description = ?, sort_order = ?, active = ?
                  WHERE id = ?',
                [...$params, $id]
            );
        }

        // Register group in check_groups so it becomes sortable
        if ($groupName !== '') {
            db_execute(
                'INSERT OR IGNORE INTO check_groups (site_id, name, sort_order) VALUES (?, ?, 0)',
                [$siteId, $groupName]
            );
        }

        header('Location: /admin/checks.php?msg=saved');
        exit;
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        db_execute('DELETE FROM checks WHERE id = ?', [$id]);
        header('Location: /admin/checks.php?msg=deleted');
        exit;
    }

    if ($action === 'update_groups') {
        $groupOrders = $_POST['group_sort_order'] ?? [];
        if (is_array($groupOrders)) {
            foreach ($groupOrders as $groupId => $order) {
                db_execute(
                    'UPDATE check_groups SET sort_order = ? WHERE id = ?',
                    [(int) $order, (int) $groupId]
                );
            }
        }
        header('Location: /admin/checks.php?msg=groups_saved');
        exit;
    }
}

// ── Data ──────────────────────────────────────────────────────────────────────

$checks = db_fetch_all(
    'SELECT c.*, s.name AS site_name
       FROM checks c
       JOIN sites  s ON s.id = c.site_id
      ORDER BY s.sort_order, s.name, c.sort_order, c.label'
);

$sites  = db_fetch_all('SELECT id, name FROM sites WHERE active = 1 ORDER BY sort_order, name');

$editId  = (int) ($_GET['edit'] ?? 0);
$editRow = $editId > 0
    ? db_fetch_one('SELECT * FROM checks WHERE id = ?', [$editId])
    : null;

// Groups with at least one existing check, for sort-order management
$groups = db_fetch_all(
    'SELECT cg.id, cg.name, cg.sort_order, cg.site_id, s.name AS site_name
       FROM check_groups cg
       JOIN sites s ON s.id = cg.site_id
      WHERE EXISTS (
                SELECT 1 FROM checks c
                 WHERE c.site_id = cg.site_id
                   AND c.group_name = cg.name
            )
      ORDER BY s.sort_order, s.name, cg.sort_order, cg.name'
);

// ── View ──────────────────────────────────────────────────────────────────────

$pageTitle = 'Checks';
$adminPage = 'checks';
require_once __DIR__ . '/../../templates/header.php';
require_once __DIR__ . '/../../templates/admin_nav.php';

$messages = [
    'saved'        => ['type' => 'success', 'text' => 'Gespeichert.'],
    'deleted'      => ['type' => 'success', 'text' => 'Gelöscht.'],
    'invalid'      => ['type' => 'error',   'text' => 'Standort, Name und Host sind Pflichtfelder.'],
    'invalid_port' => ['type' => 'error',   'text' => 'Ungültiger Port (1–65535).'],
    'groups_saved' => ['type' => 'success', 'text' => 'Gruppen-Reihenfolge gespeichert.'],
];
if (isset($messages[$msg])): ?>
    <div class="alert alert-<?= $messages[$msg]['type'] ?>">
        <?= htmlspecialchars($messages[$msg]['text']) ?>
    </div>
<?php endif ?>

<div style="display:grid;grid-template-columns:1fr 360px;gap:24px;align-items:start;">

    <!-- List -->
    <div class="card">
        <div class="card-header">Alle Checks (<?= count($checks) ?>)</div>
        <div class="card-body">
            <?php if (empty($checks)): ?>
                <p style="padding:20px;color:var(--color-muted);">Noch keine Checks.</p>
            <?php else: ?>
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="border-bottom:2px solid var(--color-border);text-align:left;">
                        <th style="padding:8px 8px 8px 16px;">Label</th>
                        <th style="padding:8px;">Host</th>
                        <th style="padding:8px;">Typ</th>
                        <th style="padding:8px;">Standort</th>
                        <th style="padding:8px;">Aktiv</th>
                        <th style="padding:8px 16px 8px 8px;"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($checks as $c): ?>
                    <tr style="border-top:1px solid var(--color-border);">
                        <td style="padding:9px 8px 9px 16px;font-weight:500;">
                            <?= htmlspecialchars($c['label']) ?>
                        </td>
                        <td style="padding:9px 8px;font-family:monospace;font-size:12px;">
                            <?= htmlspecialchars($c['host']) ?>
                            <?= ($c['type'] === 'port' && $c['port']) ? ':' . (int) $c['port'] : '' ?>
                        </td>
                        <td style="padding:9px 8px;">
                            <span class="badge badge-unknown">
                                <?= htmlspecialchars(strtoupper($c['type'])) ?>
                            </span>
                        </td>
                        <td style="padding:9px 8px;color:var(--color-muted);">
                            <?= htmlspecialchars($c['site_name']) ?>
                        </td>
                        <td style="padding:9px 8px;"><?= $c['active'] ? '✓' : '–' ?></td>
                        <td style="padding:9px 16px 9px 8px;text-align:right;white-space:nowrap;">
                            <a href="/admin/checks.php?edit=<?= $c['id'] ?>"
                               class="btn btn-sm"
                               style="background:var(--color-border);color:var(--color-text);">
                                Bearbeiten
                            </a>
                            <form method="post" action="/admin/checks.php"
                                  style="display:inline;"
                                  onsubmit="return confirm('Check löschen?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id"     value="<?= $c['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    Löschen
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
            <?php endif ?>
        </div>
    </div>

    <!-- Add / Edit form -->
    <div class="card">
        <div class="card-header">
            <?= $editRow ? 'Check bearbeiten' : 'Check hinzufügen' ?>
        </div>
        <div class="card-body" style="padding:20px;">
            <form method="post" action="/admin/checks.php" id="check-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action"
                       value="<?= $editRow ? 'edit' : 'add' ?>">
                <?php if ($editRow): ?>
                    <input type="hidden" name="id" value="<?= $editRow['id'] ?>">
                <?php endif ?>

                <div class="form-group">
                    <label for="c-site">Standort *</label>
                    <select id="c-site" name="site_id" required
                            style="width:100%;padding:8px 12px;border:1px solid var(--color-border);
                                   border-radius:var(--radius);font-size:14px;background:var(--color-surface);color:var(--color-text);">
                        <option value="">– bitte wählen –</option>
                        <?php foreach ($sites as $s): ?>
                            <option value="<?= $s['id'] ?>"
                                <?= ((int)($editRow['site_id'] ?? 0) === (int)$s['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['name']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="c-label">Label *</label>
                    <input type="text" id="c-label" name="label" required
                           value="<?= htmlspecialchars($editRow['label'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="c-host">Host / IP *</label>
                    <input type="text" id="c-host" name="host" required
                           placeholder="192.168.1.1 oder example.com"
                           value="<?= htmlspecialchars($editRow['host'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="c-type">Typ *</label>
                    <select id="c-type" name="type" onchange="togglePort(this.value)"
                            style="width:100%;padding:8px 12px;border:1px solid var(--color-border);
                                   border-radius:var(--radius);font-size:14px;background:var(--color-surface);color:var(--color-text);">
                        <?php foreach (['ping', 'https', 'port'] as $t): ?>
                            <option value="<?= $t ?>"
                                <?= (($editRow['type'] ?? 'ping') === $t) ? 'selected' : '' ?>>
                                <?= strtoupper($t) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                </div>

                <div class="form-group" id="port-group"
                     style="display:<?= (($editRow['type'] ?? '') === 'port') ? 'block' : 'none' ?>;">
                    <label for="c-port">Port *</label>
                    <input type="number" id="c-port" name="port" min="1" max="65535"
                           style="width:100%;padding:8px 12px;border:1px solid var(--color-border);
                                  border-radius:var(--radius);font-size:14px;"
                           value="<?= $editRow['port'] ?? '' ?>">
                </div>

                <div class="form-group">
                    <label for="c-group">Gruppe <span style="font-weight:400;color:var(--color-muted)">(optional)</span></label>
                    <input type="text" id="c-group" name="group_name"
                           placeholder="z.&nbsp;B. Netzwerk"
                           value="<?= htmlspecialchars($editRow['group_name'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="c-desc">Beschreibung</label>
                    <input type="text" id="c-desc" name="description"
                           value="<?= htmlspecialchars($editRow['description'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="c-sort">Reihenfolge</label>
                    <input type="number" id="c-sort" name="sort_order" min="0"
                           style="width:100%;padding:8px 12px;border:1px solid var(--color-border);
                                  border-radius:var(--radius);font-size:14px;"
                           value="<?= (int) ($editRow['sort_order'] ?? 0) ?>">
                </div>

                <div class="form-group" style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" id="c-active" name="active" value="1"
                           <?= ($editRow['active'] ?? 1) ? 'checked' : '' ?>>
                    <label for="c-active" style="margin-bottom:0;">Aktiv</label>
                </div>

                <div style="display:flex;gap:8px;margin-top:4px;">
                    <button type="submit" class="btn btn-primary">
                        <?= $editRow ? 'Speichern' : 'Hinzufügen' ?>
                    </button>
                    <?php if ($editRow): ?>
                        <a href="/admin/checks.php" class="btn"
                           style="background:var(--color-border);color:var(--color-text);">
                            Abbrechen
                        </a>
                    <?php endif ?>
                </div>
            </form>
        </div>
    </div>

</div>

<?php if (!empty($groups)): ?>
<div class="card">
    <div class="card-header">Gruppen-Reihenfolge</div>
    <div class="card-body" style="padding:20px;">
        <p style="font-size:13px;color:var(--color-muted);margin-bottom:20px;">
            Niedrigere Zahlen erscheinen zuerst. Checks ohne Gruppe werden immer ganz oben angezeigt.
        </p>
        <form method="post" action="/admin/checks.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_groups">
            <table style="border-collapse:collapse;font-size:13px;width:100%;max-width:500px;">
                <thead>
                    <tr style="border-bottom:2px solid var(--color-border);text-align:left;">
                        <th style="padding:6px 16px 6px 0;">Standort</th>
                        <th style="padding:6px 16px 6px 0;">Gruppe</th>
                        <th style="padding:6px 0;">Reihenfolge</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($groups as $g): ?>
                    <tr style="border-top:1px solid var(--color-border);">
                        <td style="padding:8px 16px 8px 0;color:var(--color-muted);">
                            <?= htmlspecialchars($g['site_name']) ?>
                        </td>
                        <td style="padding:8px 16px 8px 0;font-weight:500;">
                            <?= htmlspecialchars($g['name']) ?>
                        </td>
                        <td style="padding:8px 0;">
                            <input type="number"
                                   name="group_sort_order[<?= (int) $g['id'] ?>]"
                                   value="<?= (int) $g['sort_order'] ?>"
                                   min="0" max="9999"
                                   style="width:72px;padding:4px 8px;border:1px solid var(--color-border);
                                          border-radius:var(--radius);font-size:13px;
                                          background:var(--color-surface);color:var(--color-text);">
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
            <div style="margin-top:16px;">
                <button type="submit" class="btn btn-primary">Reihenfolge speichern</button>
            </div>
        </form>
    </div>
</div>
<?php endif ?>

<script>
function togglePort(type) {
    document.getElementById('port-group').style.display = (type === 'port') ? 'block' : 'none';
    document.getElementById('c-port').required = (type === 'port');
}
togglePort(document.getElementById('c-type').value);
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
