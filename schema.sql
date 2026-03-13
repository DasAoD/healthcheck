-- ============================================================
-- healthcheck – Datenbankschema
-- ============================================================

PRAGMA journal_mode = WAL;
PRAGMA foreign_keys = ON;

-- ------------------------------------------------------------
-- Benutzer
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT    NOT NULL UNIQUE COLLATE NOCASE,
    password_hash TEXT    NOT NULL,
    role          TEXT    NOT NULL DEFAULT 'viewer' CHECK (role IN ('admin', 'viewer')),
    active        INTEGER NOT NULL DEFAULT 1,
    created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ------------------------------------------------------------
-- Standorte
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sites (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT    NOT NULL,
    short      TEXT    NOT NULL UNIQUE COLLATE NOCASE,   -- Kürzel, z.B. "HH", "BER"
    active     INTEGER NOT NULL DEFAULT 1,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ------------------------------------------------------------
-- Checks (einzelne Überwachungspunkte)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS checks (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    site_id     INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
    label       TEXT    NOT NULL,               -- Anzeigename, z.B. "Fritzbox"
    host        TEXT    NOT NULL,               -- IP oder Hostname
    type        TEXT    NOT NULL DEFAULT 'ping' CHECK (type IN ('ping', 'https', 'port')),
    port        INTEGER,                        -- nur für type=port
    group_name  TEXT    NOT NULL DEFAULT '',    -- optionale Gruppierung im Dashboard
    description TEXT    NOT NULL DEFAULT '',
    active      INTEGER NOT NULL DEFAULT 1,
    sort_order  INTEGER NOT NULL DEFAULT 0,
    created_at  TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ------------------------------------------------------------
-- Zustände (1:1 zu checks, wird per UPSERT aktualisiert)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS states (
    check_id    INTEGER PRIMARY KEY REFERENCES checks(id) ON DELETE CASCADE,
    status      TEXT    NOT NULL DEFAULT 'unknown' CHECK (status IN ('up', 'down', 'unknown')),
    since       TEXT    NOT NULL DEFAULT (datetime('now')),  -- seit wann aktueller Status
    last_check  TEXT,                                        -- letzter Prüfzeitpunkt
    last_change TEXT,                                        -- letzter Statuswechsel
    detail      TEXT    NOT NULL DEFAULT '',                 -- Fehlermeldung / Latenz
    fail_count  INTEGER NOT NULL DEFAULT 0,                  -- konsekutive Fehler
    last_alert  TEXT,                                        -- letzter Alarmversand
    alert_count INTEGER NOT NULL DEFAULT 0                   -- gesendete Alarme pro Ausfall
);

-- ------------------------------------------------------------
-- Einstellungen (Key-Value-Store)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    key        TEXT PRIMARY KEY,
    value      TEXT NOT NULL DEFAULT '',
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ------------------------------------------------------------
-- Check-Historie (Verlauf der letzten Ergebnisse)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS check_log (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    check_id   INTEGER NOT NULL REFERENCES checks(id) ON DELETE CASCADE,
    checked_at TEXT    NOT NULL DEFAULT (datetime('now')),
    status     TEXT    NOT NULL CHECK (status IN ('up', 'down', 'unknown')),
    detail     TEXT    NOT NULL DEFAULT ''
);

CREATE INDEX IF NOT EXISTS idx_check_log_check_id   ON check_log (check_id);
CREATE INDEX IF NOT EXISTS idx_check_log_checked_at ON check_log (checked_at);
CREATE INDEX IF NOT EXISTS idx_checks_site_id       ON checks    (site_id);
CREATE INDEX IF NOT EXISTS idx_states_status        ON states    (status);

-- ------------------------------------------------------------
-- Gruppen-Reihenfolge (eine Zeile pro Standort + Gruppenname)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS check_groups (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    site_id    INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
    name       TEXT    NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    UNIQUE (site_id, name)
);

CREATE INDEX IF NOT EXISTS idx_check_groups_site ON check_groups (site_id, sort_order);

-- ------------------------------------------------------------
-- Standardeinstellungen
-- ------------------------------------------------------------
INSERT OR IGNORE INTO settings (key, value) VALUES
    ('telegram_bot_token',   ''),
    ('telegram_chat_id',     ''),
    ('mail_from',            ''),
    ('mail_to',              ''),
    ('mail_smtp_host',       ''),
    ('mail_smtp_port',       '587'),
    ('mail_smtp_user',       ''),
    ('mail_smtp_pass',       ''),
    ('notify_telegram',      '0'),
    ('notify_mail',          '0'),
    ('alert_debounce_min',        '5'),    -- Mindestwartzeit zwischen zwei Alarmen (Minuten)
    ('alert_repeat_min',          '60'),   -- Wiederholungsalarm nach X Minuten bei anhaltendem Down (Stufe 1)
    ('alert_long_down_min',       '360'),  -- Ab X Minuten Down gilt Stufe 2
    ('alert_repeat_extended_min', '1440'), -- Wiederholungsintervall in Stufe 2 (Minuten)
    ('alert_max_count',           '5'),    -- Max. Alarme pro Ausfall (0 = unbegrenzt)
    ('fail_threshold',            '2'),    -- Fehler in Folge bevor Alarm ausgelöst wird
    ('check_timeout_sec',    '10'),   -- Timeout pro Check in Sekunden
    ('log_retention_days',   '30'),   -- Alte Log-Einträge nach X Tagen löschen
    ('site_title',           'Healthcheck Dashboard');
