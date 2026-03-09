# Production Deployment

The default configuration uses a self-signed certificate suited for local development. In production you need a trusted TLS certificate and public port bindings. Two approaches are described below.

---

## Required: CollectiveAccess SSL Configuration

**Before deploying to production**, you must configure CollectiveAccess to work correctly behind an SSL-terminating reverse proxy. Without these settings, you'll encounter "too many redirects" errors.

### Option 1: Use overrides (quick development/testing)

Create these two override files that apply on container restart without rebuilding:

**File:** `overrides/providence/setup.php`

```php
<?php
# (copy the entire setup.php from providence/setup.php and add these lines)

# Protocol (http or https)
# When running behind a reverse proxy with SSL termination, set this to https
if (!defined('__CA_SITE_PROTOCOL__')) { define('__CA_SITE_PROTOCOL__', 'https'); }

# URL root path (the subdirectory under which CA is served)
# Since Providence is served under /backend/ via nginx proxy, set this explicitly
if (!defined('__CA_URL_ROOT__')) { define('__CA_URL_ROOT__', '/backend'); }
```

**File:** `overrides/providence/.htaccess`

```apache
# Copy the entire .htaccess from the container, then add:
<IfModule mod_rewrite.c>
    RewriteEngine on
    RewriteBase /backend
    
    # ... rest of rewrite rules
</IfModule>
```

Apply changes:
```bash
docker compose restart providence
```

### Option 2: Bake into image (recommended for production)

For production deployments, bake these settings into the Docker image for better performance and immutability:

1. **Edit** `providence/setup.php` and add the protocol and URL root settings:
   ```php
   # Add after __CA_USE_CLEAN_URLS__:
   if (!defined('__CA_URL_ROOT__')) { define('__CA_URL_ROOT__', '/backend'); }
   if (!defined('__CA_SITE_PROTOCOL__')) { define('__CA_SITE_PROTOCOL__', 'https'); }
   ```

2. **Create** `providence/.htaccess`:
   ```bash
   # Copy from a running container
   docker compose exec providence cat /var/www/providence/.htaccess > providence/.htaccess
   ```
   
   Then edit it to add `RewriteBase /backend` in the mod_rewrite section.

3. **Update** `providence/Dockerfile` to copy the .htaccess:
   ```dockerfile
   # Add before ENTRYPOINT:
   COPY .htaccess /var/www/providence/.htaccess
   ```

4. **Rebuild and deploy:**
   ```bash
   docker compose build providence
   docker compose up -d providence
   ```

5. **Remove** the override files (no longer needed):
   ```bash
   rm -f overrides/providence/setup.php overrides/providence/.htaccess
   ```

### Why this is necessary

- **`__CA_SITE_PROTOCOL__`**: Providence auto-detects the protocol from `$_SERVER['HTTPS']`, but when behind a reverse proxy, it sees HTTP internally. Setting this explicitly ensures all generated URLs use `https://`.
  
- **`__CA_URL_ROOT__`**: Providence is served at `/backend/` (not root), so it must know this prefix to generate correct URLs for redirects, links, and assets.

- **`RewriteBase`**: Apache's mod_rewrite needs to know the URL path prefix when rewriting URLs. Without this, clean URL routing breaks.

**Verify the fix:**
```bash
curl -k -I https://your-domain.com/backend/
# Should return: HTTP/2 302, location: /backend/index.php/system/auth/login

curl -k -I https://your-domain.com/backend/index.php/system/auth/login
# Should return: HTTP/2 200 (login page loads successfully)
```

---

## External host nginx (recommended)

Your existing system nginx (or a VM-level nginx) terminates TLS with a real certificate, then proxies over plain HTTP to the Docker stack. This is the most common setup for VPS / bare-metal deployments.

### Step 1 — Switch Docker nginx to HTTP-only

The self-signed cert and HTTPS redirect are not needed — the host nginx handles TLS. Replace `nginx/conf.d/default.conf` with an HTTP-only config:

```nginx
# nginx/conf.d/default.conf  (production, HTTP-only internal config)
resolver 127.0.0.11 valid=5s ipv6=off;

upstream providence  { server providence:80;  }
upstream pawtucket2  { server pawtucket2:80;  }

server {
    listen 80;
    server_name _;

    proxy_read_timeout    300s;
    proxy_connect_timeout 60s;
    proxy_send_timeout    300s;
    client_max_body_size  512M;

    set $up_providence http://providence:80;
    set $up_pawtucket2 http://pawtucket2:80;

    location /backend {
        proxy_pass         $up_providence;
        proxy_set_header   Host              $host;
        proxy_set_header   X-Real-IP         $remote_addr;
        proxy_set_header   X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto https;
        proxy_http_version 1.1;
    }

    location / {
        proxy_pass         $up_pawtucket2;
        proxy_set_header   Host              $host;
        proxy_set_header   X-Real-IP         $remote_addr;
        proxy_set_header   X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto https;
        proxy_http_version 1.1;
    }
}
```

### Step 2 — Expose only an internal port

In `docker-compose.yml`, change the nginx `ports:` so it only listens on localhost (the host nginx will proxy to it) and remove the cert volume:

```yaml
nginx:
  ports:
    - "127.0.0.1:8080:80"    # only reachable from the host itself
  volumes:
    - ./nginx/conf.d/default.conf:/etc/nginx/conf.d/default.conf:ro
    # no certs volume needed
```

### Step 3 — Host nginx virtual host with TLS

```nginx
# /etc/nginx/sites-available/collectiveaccess
server {
    listen 80;
    server_name catalog.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name catalog.example.com;

    ssl_certificate     /etc/letsencrypt/live/catalog.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/catalog.example.com/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         HIGH:!aNULL:!MD5;

    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Content-Type-Options    nosniff                               always;
    add_header X-Frame-Options           SAMEORIGIN                            always;

    client_max_body_size 512M;
    proxy_read_timeout   300s;
    proxy_send_timeout   300s;

    location /backend {
        proxy_pass         http://127.0.0.1:8080;
        proxy_set_header   Host              $host;
        proxy_set_header   X-Real-IP         $remote_addr;
        proxy_set_header   X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto https;
    }

    location / {
        proxy_pass         http://127.0.0.1:8080;
        proxy_set_header   Host              $host;
        proxy_set_header   X-Real-IP         $remote_addr;
        proxy_set_header   X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto https;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/collectiveaccess /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

### Step 4 — Obtain a TLS certificate

```bash
sudo certbot --nginx -d catalog.example.com
```

---

## SSL inside Docker with Certbot

If you prefer to keep everything inside the Docker stack (no host nginx), replace the self-signed certificate with a Let's Encrypt certificate.

### Add Certbot to `docker-compose.yml`

```yaml
services:

  nginx:
    image: nginx:alpine
    ports:
      - "0.0.0.0:80:80"
      - "0.0.0.0:443:443"
    volumes:
      - ./nginx/conf.d/default.conf:/etc/nginx/conf.d/default.conf:ro
      - certbot_www:/var/www/certbot:ro
      - certbot_certs:/etc/letsencrypt:ro

  certbot:
    image: certbot/certbot
    volumes:
      - certbot_www:/var/www/certbot
      - certbot_certs:/etc/letsencrypt
    # Run once to obtain cert, then comment out and use cron for renewal:
    command: >
      certonly --webroot -w /var/www/certbot
      -d catalog.example.com
      --email admin@example.com
      --agree-tos --non-interactive

volumes:
  certbot_www:
  certbot_certs:
```

### Updated nginx config

Replace `nginx/conf.d/default.conf` with a version that uses the Let's Encrypt cert paths instead of the self-signed dev cert:

```nginx
resolver 127.0.0.11 valid=5s ipv6=off;

server {
    listen 80;
    server_name catalog.example.com;

    # Certbot HTTP-01 challenge
    location /.well-known/acme-challenge/ {
        root /var/www/certbot;
    }

    location / {
        return 301 https://$host$request_uri;
    }
}

server {
    listen 443 ssl http2;
    server_name catalog.example.com;

    ssl_certificate     /etc/letsencrypt/live/catalog.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/catalog.example.com/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         HIGH:!aNULL:!MD5;

    client_max_body_size 512M;
    proxy_read_timeout   300s;

    set $up_providence http://providence:80;
    set $up_pawtucket2 http://pawtucket2:80;

    location /backend {
        proxy_pass         $up_providence;
        proxy_set_header   Host              $host;
        proxy_set_header   X-Real-IP         $remote_addr;
        proxy_set_header   X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto https;
        proxy_http_version 1.1;
    }

    location / {
        proxy_pass         $up_pawtucket2;
        proxy_set_header   Host              $host;
        proxy_set_header   X-Real-IP         $remote_addr;
        proxy_set_header   X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto https;
        proxy_http_version 1.1;
    }
}
```

### Certificate renewal

```bash
# crontab -e
0 3 * * * docker compose -f /path/to/cacompose/docker-compose.yml \
          run --rm certbot renew --quiet \
          && docker compose -f /path/to/cacompose/docker-compose.yml \
          exec nginx nginx -s reload
```

---

## Production hardening checklist

```
[ ] Configure SSL settings in Providence (see "Required: CollectiveAccess SSL Configuration" above)
[ ] Bake SSL config into image rather than using overrides (for production)
[ ] Change ALL default passwords in .env
[ ] Set MEDIA_PATH to an absolute path with a real backup strategy
[ ] Set TZ to your local timezone
[ ] CA_ADMIN_EMAIL to a real monitored address
[ ] Bind Docker nginx to 127.0.0.1 (not 0.0.0.0) when using host nginx
[ ] CA_STACKTRACE_ON_EXCEPTION must be empty/false in production
[ ] Ensure __CA_ALLOW_INSTALLER_TO_OVERWRITE_EXISTING_INSTALLS__ = false
[ ] Lock down MySQL: CA_DB_USER should not have SUPER or FILE privileges
[ ] Firewall: only allow 80/443 externally; block 3306/6379
[ ] Set up automated database backups (see database.md)
[ ] Configure Docker log rotation in /etc/docker/daemon.json:
      { "log-driver": "json-file",
        "log-opts": { "max-size": "50m", "max-file": "5" } }
[ ] Pin image versions in docker-compose.yml for reproducible builds:
      image: mysql:8.0.36  /  redis:7.2-alpine
[ ] Pin CA_PROVIDENCE_VERSION and CA_PAWTUCKET2_VERSION to a release tag
      (e.g. 2.0.2) instead of master
```

---

## Umami Analytics (opt-in)

[Umami](https://umami.is/) is a self-hosted, privacy-friendly alternative to Google Analytics. It runs as two extra containers (`umami-db` + `umami`) and is completely isolated from the CollectiveAccess stack.

### Starting Umami

Umami uses a Docker Compose **profile** so it does not start by default:

```bash
# Start the full stack including Umami
docker compose --profile analytics up -d

# Or add Umami to an already-running stack
docker compose --profile analytics up -d umami-db umami
```

Access the Umami UI at **http://127.0.0.1:3000**

Default credentials: `admin` / `umami` — **change these on first login**.

### Stopping Umami

```bash
docker compose --profile analytics stop umami umami-db
```

### Credentials

Set these in `.env` before first start (the defaults are intentionally weak):

```dotenv
UMAMI_DB_PASSWORD=strong-random-password
UMAMI_APP_SECRET=another-strong-random-secret
```

`APP_SECRET` is used to sign Umami's JWT session tokens. Changing it after first run invalidates all existing sessions.

### Wiring up Pawtucket2

1. In the Umami UI: **Settings → Websites → Add website** — enter your site's domain.
2. Copy the generated `<script>` tag (Settings → Websites → Get tracking code).
3. Paste it into your Pawtucket2 theme's footer template:
   ```
   overrides/pawtucket2/themes/default/views/pageFormat/pageFooter.tpl
   ```
   Example:
   ```html
   <script defer src="http://127.0.0.1:3000/script.js"
           data-website-id="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"></script>
   ```
   In production use the public Umami URL (behind your reverse proxy) instead of `127.0.0.1:3000`.
4. Restart Pawtucket2 to apply the override:
   ```bash
   docker compose restart pawtucket2
   ```

### Exposing Umami publicly (production)

Add a location block to your host nginx config:

```nginx
location /analytics/ {
    proxy_pass http://127.0.0.1:3000/;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

Then update the script tag to use `/analytics/script.js` instead of the direct port.
