<?php
/**
 * includes/db.php
 * Datenbankverbindung (SQLite via PDO) + Hilfsfunktionen
 */

declare(strict_types=1);

/**
 * Gibt eine gemeinsam genutzte PDO-Verbindung zur SQLite-Datenbank zurück.
 * Beim ersten Aufruf wird die Verbindung hergestellt und das Schema
 * automatisch eingespielt, falls die DB noch nicht existiert.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dbPath = env('DB_PATH', __DIR__ . '/../data/healthcheck.db');

    // Verzeichnis anlegen, falls nicht vorhanden
    $dir = dirname($dbPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }

    $isNew = !file_exists($dbPath);

    $pdo = new PDO('sqlite:' . $dbPath, options: [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Performance-Pragmas
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA synchronous  = NORMAL');
    $pdo->exec('PRAGMA busy_timeout = 5000');

    // Schema einmalig einspielen
    if ($isNew) {
        $schema = file_get_contents(__DIR__ . '/../schema.sql');
        $pdo->exec($schema);
    } else {
        // Migrations for existing databases
        $cols     = $pdo->query("PRAGMA table_info(states)")->fetchAll(PDO::FETCH_ASSOC);
        $colNames = array_column($cols, 'name');

        if (!in_array('alert_count', $colNames, true)) {
            $pdo->exec('ALTER TABLE states ADD COLUMN alert_count INTEGER NOT NULL DEFAULT 0');
        }

        $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('alert_max_count', '5')");

        // Migration: check_groups table for group sort order
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS check_groups (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                site_id    INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
                name       TEXT    NOT NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                UNIQUE (site_id, name)
            )'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_check_groups_site ON check_groups (site_id, sort_order)'
        );

        // Backfill: register all existing groups that are not yet in check_groups
        $pdo->exec(
            "INSERT OR IGNORE INTO check_groups (site_id, name, sort_order)
             SELECT DISTINCT site_id, group_name, 0
               FROM checks
              WHERE group_name != ''"
        );
    }

    return $pdo;
}

/**
 * Führt eine SELECT-Abfrage aus und gibt alle Zeilen zurück.
 *
 * @param  string $sql    SQL-Statement mit benannten Platzhaltern
 * @param  array  $params Bindungsparameter
 * @return array<int, array<string, mixed>>
 */
function db_fetch_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Führt eine SELECT-Abfrage aus und gibt die erste Zeile zurück (oder null).
 *
 * @param  string $sql
 * @param  array  $params
 * @return array<string, mixed>|null
 */
function db_fetch_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row !== false ? $row : null;
}

/**
 * Führt ein INSERT/UPDATE/DELETE aus und gibt die Anzahl betroffener Zeilen zurück.
 *
 * @param  string $sql
 * @param  array  $params
 * @return int  Anzahl betroffener Zeilen
 */
function db_execute(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/**
 * Gibt die zuletzt eingefügte Zeilen-ID zurück.
 */
function db_last_id(): string
{
    return db()->lastInsertId();
}

/**
 * Gibt einen einzelnen Skalarwert zurück (erste Spalte der ersten Zeile).
 *
 * @return mixed|null
 */
function db_scalar(string $sql, array $params = []): mixed
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $val = $stmt->fetchColumn();
    return $val !== false ? $val : null;
}

/**
 * Liest einen Einstellungswert aus der settings-Tabelle.
 */
function setting(string $key, mixed $default = ''): string
{
    static $cache = [];

    if (!isset($cache[$key])) {
        $val = db_scalar('SELECT value FROM settings WHERE key = ?', [$key]);
        $cache[$key] = $val !== null ? $val : $default;
    }

    return (string) $cache[$key];
}

/**
 * Speichert einen Einstellungswert in der settings-Tabelle.
 */
function setting_set(string $key, string $value): void
{
    db_execute(
        "INSERT INTO settings (key, value, updated_at)
         VALUES (:key, :value, datetime('now'))
         ON CONFLICT(key) DO UPDATE SET value = excluded.value,
                                        updated_at = excluded.updated_at",
        [':key' => $key, ':value' => $value]
    );
}
