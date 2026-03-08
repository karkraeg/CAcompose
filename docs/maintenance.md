# Maintenance & Troubleshooting

## Maintenance

### Rebuild after a CA version update

```bash
# Update version in .env, then:
docker compose build --no-cache providence pawtucket2
docker compose up -d
```

### Database backup and restore

See [database.md](database.md) for full backup, restore, and dump-import instructions.

### Clear the Redis cache

```bash
# Clear Providence cache (DB 0)
docker compose exec redis redis-cli -n 0 FLUSHDB

# Clear Pawtucket2 cache (DB 1)
docker compose exec redis redis-cli -n 1 FLUSHDB
```

### caUtils CLI

See [import.md](import.md) for the full caUtils command reference and examples.

### Enable the background task queue

For large media processing jobs (high-res images, video transcoding), enable the task queue and run it via CRON.

In `overrides/providence/setup.php`:
```php
define('__CA_QUEUE_ENABLED__', 1);
```

Then add a cron job on the host:
```bash
# crontab -e — run every minute
* * * * * docker compose -f /path/to/cacompose/docker-compose.yml \
          exec -T providence \
          php /var/www/providence/support/bin/caUtils process-task-queue \
          >/dev/null 2>&1
```

---

## Troubleshooting

### Containers won't start — "MEDIA_PATH must be set"

`MEDIA_PATH` is required in `.env`:
```bash
mkdir -p ./media
# In .env:
MEDIA_PATH=./media
```

### HTTPS certificate error in browser

Run `./generate-dev-certs.sh` if you haven't already, then restart nginx:
```bash
docker compose restart nginx
```
To suppress the browser warning permanently, trust the certificate — see [getting-started.md](getting-started.md#development-https).

### Nginx crashes with "host not found in upstream"

This happens when nginx starts before Docker's internal DNS has registered the upstream containers. It is resolved by the `resolver 127.0.0.11` directive and variable-based `proxy_pass` already in the config. If you see it anyway, recreate the nginx container (not just restart):
```bash
docker compose up -d --force-recreate nginx
```

### Providence installer returns a blank page or 500

1. Check PHP errors:
   ```bash
   docker compose logs providence
   docker compose exec providence tail -50 /var/log/apache2/providence-error.log
   ```
2. Verify the DB is reachable:
   ```bash
   docker compose exec providence \
     php -r "new PDO('mysql:host=db;dbname=${CA_DB_DATABASE}', '${CA_DB_USER}', '${CA_DB_PASSWORD}');"
   ```

### Media files uploaded in Providence don't appear in Pawtucket2

Both containers must mount the **same physical directory**:
```bash
docker compose exec providence  ls /var/www/providence/media
docker compose exec pawtucket2  ls /var/www/pawtucket2/media
# Both should show identical contents.

docker compose config | grep -A2 'media'
```

### Overrides aren't being applied

Overrides are applied at container **start**:
```bash
docker compose restart providence   # or pawtucket2
docker compose logs providence 2>&1 | grep -i override
# Should print: [entrypoint] Applying Providence overrides …
```

### Redis connection refused

The PHP containers wait for the `redis` healthcheck:
```bash
docker compose ps redis
docker compose logs redis
```

### Build fails — "Composer could not find a composer.json"

The GitHub clone failed (network issue or invalid branch name):
```bash
docker compose build --progress=plain providence 2>&1 | grep -A5 'clone'
```
Verify branch names in `.env`:
```dotenv
CA_PROVIDENCE_VERSION=master   # or a tag like 2.0.2
```

### Clean URLs return 404

Clean URLs are **disabled by default** (`__CA_USE_CLEAN_URLS__ = 0`). To enable:

1. In `overrides/providence/setup.php`:
   ```php
   define('__CA_USE_CLEAN_URLS__', 1);
   ```

2. In `overrides/providence/.htaccess` (copy from container first), set `RewriteBase` to match the URL prefix:
   ```apache
   RewriteBase /backend
   ```

3. Restart:
   ```bash
   docker compose restart providence
   ```
