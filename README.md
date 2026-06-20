# Healthcheck Dashboard

> **📌 Mirror-Hinweis:** Dieses Repository ist ein automatischer Spiegel.
> Die primäre Entwicklung findet auf **[git.uliana.de/DasAoD/REPONAME](https://git.uliana.de/DasAoD/REPONAME)** statt.
> Issues und Pull Requests bitte dort öffnen.

A self-hosted monitoring dashboard for multiple sites and locations.  \nBuilt with plain PHP — no framework, no Composer.

---

## Features

- Monitor hosts via **ICMP ping**, **HTTPS** and **TCP port** checks
-Multi-site / multi-location support with grouping
- Real-time status dashboard
- Admin interface
- Two-stage alert logic with configurable thresholds
- Notifications via **Telegram** and **E-Mail (SMTP)**
- Role-based access control (admin / viewer)

---

## Requirements

- PHP 8.4 (CLI + FPM)
- Nginx
- SQLite

---

## Mitwirkende

Dieses Projekt wurde in Zusammenarbeit mit [Claude](https://claude.ai) (Sonnet 4.6) von [Anthropic](https://anthropic.com) entwickelt und iterativ ausgebaut.   
Der überwiegende Teil des Codes, der Architektur und der Dokumentation wurde durch KI generiert und gemeinsam verfeinert.

| Rolle | Person / Tool |
|---|---|
| Projektidee, Anforderungen & Tests | [DasAoD](https://git.uliana.de/DasAoD) |
| Code, Architektur, Dokumentation | [Claude](https://git.uliana.de/Claude) (Anthropic) |

## License

[MIT](LICENSE)
