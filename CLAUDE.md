# healthcheck – Projektkontext für Claude Code

Self-hosted Monitoring-Dashboard für mehrere Standorte/Hosts. Reines PHP — kein Framework, kein Composer.

## Tech-Stack
- PHP 8.4 (CLI + FPM)
- nginx
- SQLite

## Struktur
- `cron/` – Cron-Skripte für periodische Checks
- `data/` – SQLite-DB
- `includes/` – Shared PHP-Funktionen
- `logs/` – Log-Dateien
- `public/` – Document Root
  - `admin/` – Admin-Interface
  - `assets/` – CSS/JS
- `templates/` – Views

## Features
- Host-Monitoring via ICMP-Ping, HTTPS- und TCP-Port-Checks
- Multi-Site/Multi-Location-Support mit Gruppierung
- Echtzeit-Status-Dashboard
- Admin-Interface
- Zweistufige Alert-Logik mit konfigurierbaren Schwellwerten
- Benachrichtigungen via Telegram und E-Mail (SMTP)
- Rollenbasierte Zugriffskontrolle (admin/viewer)

## Konventionen
- Kein Composer, keine externen PHP-Dependencies bewusst vermeiden – bei neuen Features erst prüfen, ob es sich mit Bordmitteln (PHP-Core) lösen lässt, bevor eine Library eingeführt wird
- Rollentrennung admin/viewer bei jeder neuen Funktion beachten – nicht versehentlich viewer-Zugriff auf admin-Aktionen öffnen
