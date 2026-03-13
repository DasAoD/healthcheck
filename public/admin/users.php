<?php
/**
 * public/admin/users.php
 * CRUD for users.
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

    // ── Add user ──────────────────────────────────────────────────────────────
    if ($action === 'add') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $role     = in_array($_POST['role'] ?? '', ['admin', 'viewer'], true)
                    ? $_POST['role']
                    : 'viewer';

        if ($username === '' || $password === '') {
            header('Location: /admin/users.php?msg=invalid');
            exit;
        }

        $pwCheck = auth_validate_password($password);
        if (!$pwCheck['ok']) {
            header('Location: /admin/users.php?msg=weak_pw');
            exit;
        }

        $existing = db_fetch_one(
            'SELECT id FROM users WHERE username = ? COLLATE NOCASE',
            [$username]
        );

        if ($existing !== null) {
            header('Location: /admin/users.php?msg=duplicate');
            exit;
        }

        db_execute(
            'INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)',
            [$username, auth_hash($password), $role]
        );

        header('Location: /admin/users.php?msg=saved');
        exit;
    }

    // ── Edit user ─────────────────────────────────────────────────────────────
    if ($action === 'edit') {
        $id       = (int) ($_POST['id'] ?? 0);
        $role     = in_array($_POST['role'] ?? '', ['admin', 'viewer'], true)
                    ? $_POST['role']
                    : 'viewer';
        $active   = isset($_POST['active']) ? 1 : 0;
        $password = (string) ($_POST['password'] ?? '');

        // Prevent disabling yourself
        if ($id === (int) $user['id'] && !$active) {
            header('Location: /admin/users.php?msg=self_deactivate');
            exit;
        }

        if ($password !== '') {
            $pwCheck = auth_validate_password($password);
            if (!$pwCheck['ok']) {
                header('Location: /admin/users.php?msg=weak_pw');
                exit;
            }
            db_execute(
                'UPDATE users SET role = ?, active = ?, password_hash = ? WHERE id = ?',
                [$role, $active, auth_hash($password), $id]
            );
        } else {
            db_execute(
                'UPDATE users SET role = ?, active = ? WHERE id = ?',
                [$role, $active, $id]
            );
        }

        header('Location: /admin/users.php?msg=saved');
        exit;
    }

    // ── Delete user ───────────────────────────────────────────────────────────
    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id === (int) $user['id']) {
            header('Location: /admin/users.php?msg=self_delete');
            exit;
        }

        db_execute('DELETE FROM users WHERE id = ?', [$id]);
        header('Location: /admin/users.php?msg=deleted');
        exit;
    }
}

// ── Data ──────────────────────────────────────────────────────────────────────

$users  = db_fetch_all('SELECT id, username, role, active, created_at FROM users ORDER BY username');
$editId  = (int) ($_GET['edit'] ?? 0);
$editRow = $editId > 0
    ? db_fetch_one('SELECT id, username, role, active FROM users WHERE id = ?', [$editId])
    : null;

// ── View ──────────────────────────────────────────────────────────────────────

$pageTitle = 'Benutzer';
$adminPage = 'users';
require_once __DIR__ . '/../../templates/header.php';
require_once __DIR__ . '/../../templates/admin_nav.php';

$messages = [
    'saved'           => ['type' => 'success', 'text' => 'Gespeichert.'],
    'deleted'         => ['type' => 'success', 'text' => 'Benutzer gelöscht.'],
    'invalid'         => ['type' => 'error',   'text' => 'Benutzername und Passwort sind Pflichtfelder.'],
    'weak_pw'         => ['type' => 'error',   'text' => 'Passwort muss mindestens 8 Zeichen haben.'],
    'duplicate'       => ['type' => 'error',   'text' => 'Dieser Benutzername ist bereits vergeben.'],
    'self_delete'     => ['type' => 'error',   'text' => 'Du kannst dich nicht selbst löschen.'],
    'self_deactivate' => ['type' => 'error',   'text' => 'Du kannst dich nicht selbst deaktivieren.'],
];
if (isset($messages[$msg])): ?>
    <div class="alert alert-<?= $messages[$msg]['type'] ?>">
        <?= htmlspecialchars($messages[$msg]['text']) ?>
    </div>
<?php endif ?>

<div style="display:grid;grid-template-columns:1fr 320px;gap:24px;align-items:start;">

    <!-- List -->
    <div class="card">
        <div class="card-header">Alle Benutzer (<?= count($users) ?>)</div>
        <div class="card-body">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="border-bottom:2px solid var(--color-border);text-align:left;">
                        <th style="padding:8px 8px 8px 16px;">Benutzer</th>
                        <th style="padding:8px;">Rolle</th>
                        <th style="padding:8px;">Aktiv</th>
                        <th style="padding:8px;">Angelegt</th>
                        <th style="padding:8px 16px 8px 8px;"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr style="border-top:1px solid var(--color-border);"
                        <?= ((int)$u['id'] === (int)$user['id']) ? 'class="row-self"' : '' ?>>
                        <td style="padding:9px 8px 9px 16px;font-weight:500;">
                            <?= htmlspecialchars($u['username']) ?>
                            <?php if ((int)$u['id'] === (int)$user['id']): ?>
                                <span style="font-size:11px;color:var(--color-muted);font-weight:400;">(du)</span>
                            <?php endif ?>
                        </td>
                        <td style="padding:9px 8px;">
                            <span class="badge <?= $u['role'] === 'admin' ? 'badge-down' : 'badge-unknown' ?>">
                                <?= htmlspecialchars($u['role']) ?>
                            </span>
                        </td>
                        <td style="padding:9px 8px;"><?= $u['active'] ? '✓' : '–' ?></td>
                        <td style="padding:9px 8px;color:var(--color-muted);font-size:12px;">
                            <?= htmlspecialchars(substr($u['created_at'], 0, 10)) ?>
                        </td>
                        <td style="padding:9px 16px 9px 8px;text-align:right;white-space:nowrap;">
                            <a href="/admin/users.php?edit=<?= $u['id'] ?>"
                               class="btn btn-sm"
                               style="background:var(--color-border);color:var(--color-text);">
                                Bearbeiten
                            </a>
                            <?php if ((int)$u['id'] !== (int)$user['id']): ?>
                            <form method="post" action="/admin/users.php"
                                  style="display:inline;"
                                  onsubmit="return confirm('Benutzer löschen?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id"     value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    Löschen
                                </button>
                            </form>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add / Edit form -->
    <div class="card">
        <div class="card-header">
            <?= $editRow ? 'Benutzer bearbeiten' : 'Benutzer hinzufügen' ?>
        </div>
        <div class="card-body" style="padding:20px;">
            <form method="post" action="/admin/users.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action"
                       value="<?= $editRow ? 'edit' : 'add' ?>">
                <?php if ($editRow): ?>
                    <input type="hidden" name="id" value="<?= $editRow['id'] ?>">
                <?php endif ?>

                <?php if (!$editRow): ?>
                <div class="form-group">
                    <label for="u-name">Benutzername *</label>
                    <input type="text" id="u-name" name="username" required
                           autocomplete="off"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
                <?php else: ?>
                <p style="font-weight:500;margin-bottom:16px;">
                    <?= htmlspecialchars($editRow['username']) ?>
                </p>
                <?php endif ?>

                <div class="form-group">
                    <label for="u-pw">
                        Passwort <?= $editRow ? '<span style="font-weight:400;color:var(--color-muted)">(leer = unverändert)</span>' : '*' ?>
                    </label>
                    <input type="password" id="u-pw" name="password"
                           autocomplete="new-password"
                           <?= $editRow ? '' : 'required' ?>>
                </div>

                <div class="form-group">
                    <label for="u-role">Rolle</label>
                    <select id="u-role" name="role"
                            style="width:100%;padding:8px 12px;border:1px solid var(--color-border);
                                   border-radius:var(--radius);font-size:14px;background:var(--color-surface);color:var(--color-text);">
                        <option value="viewer" <?= (($editRow['role'] ?? 'viewer') === 'viewer') ? 'selected' : '' ?>>
                            Viewer
                        </option>
                        <option value="admin" <?= (($editRow['role'] ?? '') === 'admin') ? 'selected' : '' ?>>
                            Admin
                        </option>
                    </select>
                </div>

                <?php if ($editRow): ?>
                <div class="form-group" style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" id="u-active" name="active" value="1"
                           <?= $editRow['active'] ? 'checked' : '' ?>>
                    <label for="u-active" style="margin-bottom:0;">Aktiv</label>
                </div>
                <?php endif ?>

                <div style="display:flex;gap:8px;margin-top:4px;">
                    <button type="submit" class="btn btn-primary">
                        <?= $editRow ? 'Speichern' : 'Hinzufügen' ?>
                    </button>
                    <?php if ($editRow): ?>
                        <a href="/admin/users.php" class="btn"
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
