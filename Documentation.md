# CAcompose — Documentation

Docker Compose setup for running **Providence** (backend admin) and **Pawtucket2** (public catalog) side-by-side with MySQL 8, Redis 7, and Nginx.

---

## Guides

| Guide | Contents |
|-------|----------|
| [Getting Started](docs/getting-started.md) | Architecture overview, quick start, dev HTTPS, first-time installation |
| [Configuration](docs/configuration.md) | All `.env` variables, external media directory, runtime data directories |
| [Customizing](docs/customizing.md) | File override system, themes, custom InformationService plugins |
| [Import](docs/import.md) | caUtils CLI reference, batch media import |
| [Database](docs/database.md) | Backup, restore, dump import, drop & recreate |
| [Production](docs/production.md) | Host nginx + TLS, Certbot inside Docker, hardening checklist, Umami analytics |
| [Maintenance & Troubleshooting](docs/maintenance.md) | Upgrades, cache clearing, task queue, common error fixes |
