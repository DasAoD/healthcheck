<?php
/**
 * public/admin/sites.php
 * CRUD for sites (locations).
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
        $name      = trim((string) ($_POST['name']       ?? ''));
        $short     = strtoupper(trim((string) ($_POST['short']      ?? '')));
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);
        $active    = isset($_POST['active']) ? 1 : 0;

        if ($name === '' || $short === '') {
            header('Location: /admin/sites.php?msg=invalid');
            exit;
        }

        if ($action === 'add') {
            db_execute(
                'INSERT INTO sites (name, short, sort_order, active) VALUES (?, ?, ?, ?)',
                [$name, $short, $sortOrder, $active]
            );
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            db_execute(
                'UPDATE sites SET name = ?, short = ?, sort_order = ?, active = ? WHERE id = ?',
                [$name, $short, $sortOrder, $active, $id]
            );
        }

        header('Location: /admin/sites.php?msg=saved');
        exit;
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        db_execute('DELETE FROM sites WHERE id = ?', [$id]);
        header('Location: /admin/sites.php?msg=deleted');
        exit;
    }
}

// ── Data ──────────────────────────────────────────────────────────────────────

$sites  = db_fetch_all('SELECT * FROM sites ORDER BY sort_order, name');
$editId = (int) ($_GET['edit'] ?? 0);
$editRow = $editId > 0
    ? db_fetch_one('SELECT * FROM sites WHERE id = ?', [$editId])
    : null;

// ── View ──────────────────────────────────────────────────────────────────────

$pageTitle = 'Standorte';
$adminPage = 'sites';
require_once __DIR__ . '/../../templates/header.php';
require_once __DIR__ . '/../../templates/admin_nav.php';

$messages = [
    'saved'   => ['type' => 'success', 'text' => 'Gespeichert.'],
    'deleted' => ['type' => 'success', 'text' => 'Gelöscht.'],
    'invalid' => ['type' => 'error',   'text' => 'Name und Kürzel sind Pflichtfelder.'],
];
if (isset($messages[$msg])): ?>
    <div class="alert alert-<?= $messages[$msg]['type'] ?>">
        <?= htmlspecialchars($messages[$msg]['text']) ?>
    </div>
<?php endif ?>

<div style="display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;">

    <!-- List -->
    <div class="card">
        <div class="card-header">Alle Standorte (<?= count($sites) ?>)</div>
        <div class="card-body">
            <?php if (empty($sites)): ?>
                <p style="padding:20px;color:var(--color-muted);">Noch keine Standorte.</p>
            <?php else: ?>
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="border-bottom:2px solid var(--color-border);text-align:left;">
                        <th style="padding:8px 16px;">Name</th>
                        <th style="padding:8px 8px;">Kürzel</th>
                        <th style="padding:8px 8px;">Reihenfolge</th>
                        <th style="padding:8px 8px;">Aktiv</th>
                        <th style="padding:8px 16px 8px 8px;"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($sites as $s): ?>
                    <tr style="border-top:1px solid var(--color-border);">
                        <td style="padding:9px 16px;font-weight:500;">
                            <?= htmlspecialchars($s['name']) ?>
                        </td>
                        <td style="padding:9px 8px;font-family:monospace;">
                            <?= htmlspecialchars($s['short']) ?>
                        </td>
                        <td style="padding:9px 8px;color:var(--color-muted);">
                            <?= (int) $s['sort_order'] ?>
                        </td>
                        <td style="padding:9px 8px;">
                            <?= $s['active'] ? '✓' : '–' ?>
                        </td>
                        <td style="padding:9px 16px 9px 8px;text-align:right;white-space:nowrap;">
                            <a href="/admin/sites.php?edit=<?= $s['id'] ?>"
                               class="btn btn-sm"
                               style="background:var(--color-border);color:var(--color-text);">
                                Bearbeiten
                            </a>
                            <form method="post" action="/admin/sites.php"
                                  style="display:inline;"
                                  onsubmit="return confirm('Standort und alle zugehörigen Checks löschen?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id"     value="<?= $s['id'] ?>">
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
            <?= $editRow ? 'Standort bearbeiten' : 'Standort hinzufügen' ?>
        </div>
        <div class="card-body" style="padding:20px;">
            <form method="post" action="/admin/sites.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action"
                       value="<?= $editRow ? 'edit' : 'add' ?>">
                <?php if ($editRow): ?>
                    <input type="hidden" name="id" value="<?= $editRow['id'] ?>">
                <?php endif ?>

                <div class="form-group">
                    <label for="s-name">Name *</label>
                    <input type="text" id="s-name" name="name" required
                           value="<?= htmlspecialchars($editRow['name'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="s-short">Kürzel * <span style="font-weight:400;color:var(--color-muted)">(z.&nbsp;B. HH)</span></label>
                    <input type="text" id="s-short" name="short" required maxlength="10"
                           style="text-transform:uppercase;"
                           value="<?= htmlspecialchars($editRow['short'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="s-sort">Reihenfolge</label>
                    <input type="number" id="s-sort" name="sort_order" min="0"
                           style="width:100%;padding:8px 12px;border:1px solid var(--color-border);
                                  border-radius:var(--radius);font-size:14px;"
                           value="<?= (int) ($editRow['sort_order'] ?? 0) ?>">
                </div>

                <div class="form-group" style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" id="s-active" name="active" value="1"
                           <?= ($editRow['active'] ?? 1) ? 'checked' : '' ?>>
                    <label for="s-active" style="margin-bottom:0;">Aktiv</label>
                </div>

                <div style="display:flex;gap:8px;margin-top:4px;">
                    <button type="submit" class="btn btn-primary">
                        <?= $editRow ? 'Speichern' : 'Hinzufügen' ?>
                    </button>
                    <?php if ($editRow): ?>
                        <a href="/admin/sites.php" class="btn"
                           style="background:var(--color-border);color:var(--color-text);">
                            Abbrechen
                        </a>
                    <?php endif ?>
                </div>
            </form>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
