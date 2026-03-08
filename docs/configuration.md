# Configuration Reference

All configuration lives in `.env`. Every value is injected into the containers as an environment variable and read by `setup.php` via `getenv()`.

## Database

| Variable              | Default | Description                                           |
|-----------------------|---------|-------------------------------------------------------|
| `MYSQL_ROOT_PASSWORD` | —       | MySQL root password (used only by the `db` container) |
| `CA_DB_HOST`          | `db`    | Database hostname (the Docker service name)           |
| `CA_DB_PORT`          | `3306`  | Database port                                         |
| `CA_DB_USER`          | —       | Application database user                             |
| `CA_DB_PASSWORD`      | —       | Application database password                         |
| `CA_DB_DATABASE`      | —       | Database name                                         |

## Application

| Variable              | Default               | Description                                  |
|-----------------------|-----------------------|----------------------------------------------|
| `CA_APP_DISPLAY_NAME` | `My CollectiveAccess` | Human-readable site name                     |
| `CA_ADMIN_EMAIL`      | `admin@example.com`   | Admin contact address                        |
| `CA_DEFAULT_LOCALE`   | `en_US`               | Default UI locale                            |
| `TZ`                  | `UTC`                 | PHP / system timezone (e.g. `Europe/Vienna`) |

## Cache (Redis)

| Variable                 | Default | Description                                        |
|--------------------------|---------|----------------------------------------------------|
| `CA_CACHE_BACKEND`       | `redis` | `redis`, `file`, `memcached`, or `apc`             |
| `CA_REDIS_HOST`          | `redis` | Redis hostname                                     |
| `CA_REDIS_PORT`          | `6379`  | Redis port                                         |
| `CA_PROVIDENCE_REDIS_DB` | `0`     | Redis DB index for Providence                      |
| `CA_PAWTUCKET2_REDIS_DB` | `1`     | Redis DB index for Pawtucket2 (separate namespace) |

## Media

| Variable     | Default   | Description                                                                                                                                                                                         |
|--------------|-----------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `MEDIA_PATH` | `./media` | **Required.** Host path mounted as the media directory in both containers. Must be an absolute path or relative to `docker-compose.yml`. See [External Media Directory](#external-media-directory). |

## Pawtucket2

| Variable              | Default   | Description                              |
|-----------------------|-----------|------------------------------------------|
| `CA_PAWTUCKET2_THEME` | `default` | Active theme folder name under `themes/` |

## Build

| Variable                | Default  | Description                                |
|-------------------------|----------|--------------------------------------------|
| `CA_PROVIDENCE_VERSION` | `master` | Git branch or tag to build Providence from |
| `CA_PAWTUCKET2_VERSION` | `master` | Git branch or tag to build Pawtucket2 from |

## Optional (not set by default)

| Variable                         | Description                                        |
|----------------------------------|----------------------------------------------------|
| `CA_GOOGLE_MAPS_KEY`             | Google Maps API key                                |
| `CA_GOOGLE_RECAPTCHA_KEY`        | reCAPTCHA v2 site key (Pawtucket2 contact forms)   |
| `CA_GOOGLE_RECAPTCHA_SECRET_KEY` | reCAPTCHA v2 secret key                            |
| `CA_STACKTRACE_ON_EXCEPTION`     | Set to `1` during development for PHP stack traces |

---

## External Media Directory

### Why this is required

Providence stores all uploaded media (images, PDFs, audio, video) under its `media/` directory. Pawtucket2 must be able to serve these same files. Both containers therefore mount the **same host path** at their respective `media/` directories.

Using a named Docker volume would lock your media inside Docker's internal storage and make backup, NFS mounting, and S3 syncing harder. A host-path bind mount keeps media accessible at a known path.

### Default setup (local directory)

```dotenv
# .env
MEDIA_PATH=./media
```

This creates `<project-root>/media/` on the host. Fine for local development or a single-server deployment.

### Absolute path (recommended for production)

```dotenv
# .env
MEDIA_PATH=/srv/ca-media
```

```bash
sudo mkdir -p /srv/ca-media
sudo chown -R 33:33 /srv/ca-media   # 33 = www-data UID in Debian-based images
```

### NFS mount (multi-server / HA)

If you run the containers across multiple hosts (e.g. Docker Swarm), the media path must be a shared network filesystem. Mount an NFS export at the same path on every node before starting the stack:

```bash
# On each Docker host
mount -t nfs nfs-server:/exports/ca-media /srv/ca-media

# Or permanently in /etc/fstab:
nfs-server:/exports/ca-media  /srv/ca-media  nfs  defaults,_netdev  0 0
```

Then set `MEDIA_PATH=/srv/ca-media` in `.env`.

### S3 / object storage

For cloud deployments, mount an S3 bucket using `s3fs` or `goofys` at the `MEDIA_PATH` before starting Docker. CollectiveAccess also has native S3 support via Flysystem — see the `app/conf/media_volumes.conf` override for that approach.

### Checking the mount

```bash
# Verify both containers see the same files
docker compose exec providence ls /var/www/providence/media
docker compose exec pawtucket2  ls /var/www/pawtucket2/media
```

---

## Runtime Data Directories

Application logs and temporary files are written to **bind-mounted host directories** so they survive container rebuilds and are accessible from the host without `docker exec`.

### Directory layout

```
data/
├── providence/
│   ├── log/    ← CA-level log files (not Apache access/error logs)
│   └── tmp/    ← sessions, upload staging, file cache
└── pawtucket2/
    ├── log/
    └── tmp/
```

These are created automatically the first time the containers start (the entrypoint runs `mkdir -p` on each path). You can also create them manually before first run:

```bash
mkdir -p data/providence/log data/providence/tmp \
         data/pawtucket2/log data/pawtucket2/tmp
```

### Tailing logs

```bash
# CA application log (errors, warnings)
tail -f data/providence/log/php_error.log

# Apache access log (inside the container — not bind-mounted)
docker compose logs -f providence
```

### Clearing the tmp cache

If you see stale session or cache issues:

```bash
# Remove everything except .gitkeep
find data/providence/tmp -mindepth 1 ! -name '.gitkeep' -delete
find data/pawtucket2/tmp -mindepth 1 ! -name '.gitkeep' -delete
docker compose restart providence pawtucket2
```
