# CAcompose

Docker Compose setup for [CollectiveAccess](https://www.collectiveaccess.org/) — runs **Providence** (cataloguing backend) and **Pawtucket2** (public catalog) together with MySQL 8, Redis 7, and Nginx, all on a single host.

## Services

| Container    | Role                                       | URL                     |
|--------------|--------------------------------------------|-------------------------|
| `nginx`      | Reverse proxy, TLS termination             | —                       |
| `providence` | Admin / cataloguing backend (PHP + Apache) | `https://host/backend/` |
| `pawtucket2` | Public catalog frontend (PHP + Apache)     | `https://host/`         |
| `db`         | MySQL 8                                    | internal                |
| `redis`      | Cache                                      | internal                |

Both apps share the same database and a single media directory mounted from the host.

## Quick start

```bash
# 1. Edit credentials (file already exists with defaults)
$EDITOR .env

# 2. Generate a self-signed TLS certificate for local HTTPS
./generate-dev-certs.sh

# 3. Create required host directories
mkdir -p ./media ./import \
         data/providence/log data/providence/tmp \
         data/pawtucket2/log data/pawtucket2/tmp

# 4. Build and start  (~20-40 min first time — Composer installs for both apps)
docker compose up --build -d

# 5. Run the web installers
open https://127.0.0.1/backend/install/   # Providence first
```

## Key features

- **Single `.env` file** — all credentials and settings in one place; `setup.php` for both apps reads them via `getenv()` at runtime, no rebuild needed for config changes.
- **File override system** — replace any app file (config, plugin, template) by placing it under `overrides/providence/` or `overrides/pawtucket2/`, mirroring the app directory structure. Applied on every container start, no rebuild.
- **Custom themes** — drop a theme folder into `overrides/pawtucket2/themes/` and set `CA_PAWTUCKET2_THEME=<name>` in `.env`.
- **External media directory** — both containers mount the same host path (`MEDIA_PATH` in `.env`) so uploaded files are shared. Supports local directories, NFS, or S3 via `s3fs`.
- **Persistent logs and tmp** — `app/log/` and `app/tmp/` for both apps are bind-mounted under `data/` on the host, surviving container rebuilds.
- **Batch media import** — drop files into `./import/` (configurable via `IMPORT_PATH`) and they appear in Providence's media importer.
- **Umami analytics** — opt-in self-hosted analytics via `docker compose --profile analytics up -d`.
- **Development HTTPS** — `./generate-dev-certs.sh` creates a self-signed cert with correct SAN for `127.0.0.1`/`localhost`. HTTP on `:80` auto-redirects to HTTPS on `:443`.
- **Production-ready routing** — Nginx uses Docker's internal DNS resolver with variable-based `proxy_pass` to avoid startup race conditions.

## File layout

```
.
├── .env                          # credentials and runtime config
├── generate-dev-certs.sh         # one-shot dev certificate generator
├── docker-compose.yml
├── nginx/
│   ├── conf.d/default.conf       # reverse proxy config (HTTPS)
│   └── certs/                    # dev.crt + dev.key (gitignored)
├── providence/
│   ├── Dockerfile
│   ├── apache.conf               # Alias /backend → /var/www/providence
│   └── setup.php                 # reads CA_* env vars via getenv()
├── pawtucket2/
│   ├── Dockerfile
│   ├── apache.conf
│   └── setup.php
├── overrides/
│   ├── providence/               # place overrides here
│   └── pawtucket2/
│       ├── app/conf/             # e.g. app.conf
│       └── themes/               # custom themes
├── data/
│   ├── providence/
│   │   ├── log/                  # CA application logs (bind-mounted)
│   │   └── tmp/                  # sessions, upload staging, file cache
│   └── pawtucket2/
│       ├── log/
│       └── tmp/
├── import/                       # batch media import staging (IMPORT_PATH)
└── media/                        # shared media directory (MEDIA_PATH)
```

## Documentation

See **[Documentation.md](Documentation.md)** for the full reference:

- Configuration variable reference
- How the file override system works
- Adding a custom InformationService plugin
- Runtime data directories (logs, tmp, import)
- Batch media import staging
- Umami analytics opt-in
- Production deployment (external nginx + Let's Encrypt, or Certbot in Docker)
- Database backup / restore
- caUtils CLI examples
- Troubleshooting

## Requirements

- Docker 24+ and Docker Compose v2
- `openssl` on the host (for `generate-dev-certs.sh`) — comes with macOS and any Linux distro
- ~4 GB disk space for the built images

## Notes

- First build clones both CA repos from GitHub and runs `composer install` — expect some time to build.
- Pin `CA_PROVIDENCE_VERSION` and `CA_PAWTUCKET2_VERSION` in `.env` to a release tag (e.g. `2.0.2`) for reproducible builds.
