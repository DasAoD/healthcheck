<?php
/**
 * includes/env.php
 * Minimaler .env-Loader – lädt Schlüssel=Wert-Paare in $_ENV.
 * Muss als Erstes eingebunden werden (vor db.php, auth.php etc.).
 */

declare(strict_types=1);

/**
 * Lädt die .env-Datei aus $path in $_ENV / putenv().
 * Bereits gesetzte Variablen werden nicht überschrieben.
 */
function load_env(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // Kommentare und leere Zeilen überspringen
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);

        // Anführungszeichen entfernen
        if (
            strlen($val) >= 2
            && (
                (str_starts_with($val, '"') && str_ends_with($val, '"'))
                || (str_starts_with($val, "'") && str_ends_with($val, "'"))
            )
        ) {
            $val = substr($val, 1, -1);
        }

        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $val;
            putenv("$key=$val");
        }
    }
}

/**
 * Gibt einen .env-Wert zurück oder $default, falls nicht gesetzt.
 */
function env(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// Automatisch laden, wenn Datei existiert
load_env(dirname(__DIR__) . '/.env');

// Zeitzone setzen
$tz = env('APP_TIMEZONE', 'Europe/Berlin');
date_default_timezone_set($tz);
