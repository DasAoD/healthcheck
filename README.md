# Healthcheck Dashboard

A self-hosted monitoring dashboard for multiple sites and locations.  
Built with plain PHP — no framework, no Composer.

---

## Features

- Monitor hosts via **ICMP ping**, **HTTPS** and **TCP port** checks
- Multi-site / multi-location support with grouping
- Real-time status dashboard with per-site overview
- Admin interface for managing sites, checks and users
- Two-stage alert logic with configurable thresholds and repeat intervals
- Notifications via **Telegram** and **E-Mail (SMTP)**
- Recovery alerts when a host comes back up
- Configurable log retention and check timeout
- Role-based access control (admin / viewer)
- CSRF protection

---

## Requirements

- PHP 8.4 (CLI + FPM)
- Nginx
- SQLite (via PHP PDO)
- `ping` and `curl` available on the system

---

## Installation

1. Clone the repository to your server:
   ```
   git clone https://github.com/DasAoD/healthcheck /var/www/healthcheck
   ```

2. Copy the environment file and fill in your values:
   ```
   cp .env.example .env
   ```

3. Set correct ownership:
   ```
   chown -R www-data:www-data /var/www/healthcheck
   ```

4. The SQLite database and default admin account are created automatically on first request.

   Default credentials:
   - Username: `admin`
   - Password: `changeme123`

   **Change the password immediately after first login.**

---

## Configuration

All settings are managed in two places:

- **`.env`** — environment-level settings (database path, app URL, session secret, SMTP credentials)
- **Admin → Settings** — runtime settings (alert thresholds, notification channels, log retention)

The `.env` file is never committed to the repository. Use `.env.example` as a template.

---

## Project Structure

```
healthcheck/
├── api/                    # JSON API endpoints
├── cron/
│   └── run.php             # Cron runner (CLI only, no HTTP context)
├── data/                   # SQLite database (not in git)
├── includes/
│   ├── auth.php            # Session, login, roles
│   ├── checker.php         # Check execution (ping, https, port)
│   ├── csrf.php            # CSRF token handling
│   ├── db.php              # PDO helpers (db_fetch_all, db_execute, setting, …)
│   ├── env.php             # .env loader
│   └── notifier.php        # Alert dispatching (Telegram + SMTP)
├── logs/                   # Application logs (not in git)
├── public/
│   ├── admin/              # Admin UI (sites, checks, users, settings)
│   ├── assets/             # CSS + JS
│   ├── index.php           # Dashboard
│   ├── login.php
│   └── logout.php
├── templates/              # Shared HTML partials
├── .env.example            # Environment variable template
├── .gitignore
├── CLAUDE.md               # Claude Code project rules
└── schema.sql              # Database schema
```

---

## Cron Setup

The check runner must be executed regularly via cron:

```
* * * * * php /var/www/healthcheck/cron/run.php >> /var/www/healthcheck/logs/cron.log 2>&1
```

---

## Alert Logic

Notifications follow a two-stage debounce model:

| Stage | Condition | Repeat interval |
|-------|-----------|-----------------|
| First alert | `fail_count >= fail_threshold` | immediately |
| Stage 1 | down < `alert_long_down_min` | every `alert_repeat_min` |
| Stage 2 | down >= `alert_long_down_min` | every `alert_repeat_extended_min` |
| Recovery | status flips to `up` | immediately |

All values are configurable in Admin → Settings.

---

## Database Schema

| Table | Purpose |
|-------|---------|
| `users` | Login accounts (roles: `admin`, `viewer`) |
| `sites` | Locations / groups |
| `checks` | Individual monitoring targets |
| `states` | Current status per check (1:1, updated via UPSERT) |
| `check_log` | Historical check results |
| `check_groups` | Group sort order per site |
| `settings` | Key-value store for runtime configuration |

---

## License

[MIT](LICENSE)
