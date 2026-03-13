<?php
/**
 * includes/auth.php
 * Authentifizierung: Session-Verwaltung, Login, Rollenprüfung
 */

declare(strict_types=1);

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/db.php';

// ── Session starten ──────────────────────────────────────────────────────────

function session_start_secure(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $secret = env('SESSION_SECRET', 'changeme');

    session_name('hc_sess');
    session_set_cookie_params([
        'lifetime' => 0,            // bis Browser-Schließung
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    // Session-Fixierung verhindern: neue ID bei jeder neuen Session
    if (empty($_SESSION['_init'])) {
        session_regenerate_id(true);
        $_SESSION['_init'] = true;
    }
}

// ── Authentifizierungsfunktionen ─────────────────────────────────────────────

/**
 * Gibt den aktuell eingeloggten User zurück oder null.
 *
 * @return array<string, mixed>|null
 */
function auth_user(): ?array
{
    session_start_secure();
    $id = $_SESSION['user_id'] ?? null;

    if ($id === null) {
        return null;
    }

    return db_fetch_one(
        'SELECT id, username, role, active FROM users WHERE id = ? AND active = 1',
        [$id]
    );
}

/**
 * Prüft, ob jemand eingeloggt ist. Leitet bei Bedarf weiter.
 *
 * @param  string|null $redirectTo  Ziel-URL für Redirect (null = nur bool zurückgeben)
 * @return array<string, mixed>     User-Datensatz
 */
function auth_require(string $redirectTo = '/public/login.php'): array
{
    $user = auth_user();

    if ($user === null) {
        header('Location: ' . $redirectTo);
        exit;
    }

    return $user;
}

/**
 * Prüft, ob der eingeloggte User Admin ist.
 * Leitet Nicht-Admins auf die Dashboard-Startseite.
 */
function auth_require_admin(string $redirectTo = '/public/index.php'): array
{
    $user = auth_require();

    if ($user['role'] !== 'admin') {
        header('Location: ' . $redirectTo);
        exit;
    }

    return $user;
}

/**
 * Versucht, einen User einzuloggen.
 *
 * @return array{ok: bool, error?: string}
 */
function auth_login(string $username, string $password): array
{
    if (trim($username) === '' || $password === '') {
        return ['ok' => false, 'error' => 'Benutzername und Passwort erforderlich.'];
    }

    $user = db_fetch_one(
        'SELECT id, username, password_hash, role, active FROM users WHERE username = ? COLLATE NOCASE',
        [trim($username)]
    );

    if ($user === null || !$user['active']) {
        // Timing-sicheres Dummy-Prüfen
        password_verify($password, '$2y$12$invalidhashinvalidhashinvalidha');
        return ['ok' => false, 'error' => 'Ungültige Anmeldedaten.'];
    }

    if (!password_verify($password, $user['password_hash'])) {
        return ['ok' => false, 'error' => 'Ungültige Anmeldedaten.'];
    }

    session_start_secure();
    session_regenerate_id(true);

    $_SESSION['user_id']   = $user['id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['logged_at'] = time();

    // Passwort-Hash bei Bedarf neu hashen (bei geändertem cost-Faktor)
    if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
        db_execute(
            'UPDATE users SET password_hash = ? WHERE id = ?',
            [password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), $user['id']]
        );
    }

    return ['ok' => true];
}

/**
 * Loggt den aktuellen User aus und zerstört die Session.
 */
function auth_logout(): void
{
    session_start_secure();
    $_SESSION = [];
    session_destroy();
    setcookie(session_name(), '', time() - 3600, '/');
}

// ── Passwort-Utilities ───────────────────────────────────────────────────────

/**
 * Erstellt einen sicheren Passwort-Hash (bcrypt, cost 12).
 */
function auth_hash(string $password): string
{
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Prüft ein Passwort auf Mindestanforderungen.
 *
 * @return array{ok: bool, error?: string}
 */
function auth_validate_password(string $password): array
{
    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'Passwort muss mindestens 8 Zeichen lang sein.'];
    }

    return ['ok' => true];
}

// ── Setup: Ersten Admin anlegen ──────────────────────────────────────────────

/**
 * Legt den initialen Admin-Account an, falls keine User existieren.
 * Wird beim ersten Aufruf von db() automatisch genutzt.
 */
function auth_ensure_default_admin(): void
{
    $count = (int) db_scalar('SELECT COUNT(*) FROM users');

    if ($count > 0) {
        return;
    }

    $default = [
        'username' => 'admin',
        'password' => 'changeme123',
    ];

    db_execute(
        'INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)',
        [$default['username'], auth_hash($default['password']), 'admin']
    );
}
