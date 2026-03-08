# CAcompose — CollectiveAccess via Docker Compose

Docker Compose setup for running **Providence** (backend admin) and **Pawtucket2** (public catalog) side-by-side with MySQL 8, Redis 7, and Nginx.

---

## Table of Contents

1. [Architecture](#architecture)
2. [Quick Start](#quick-start)
3. [Configuration Reference](#configuration-reference)
4. [Development HTTPS](#development-https)
5. [First-time Installation](#first-time-installation)
6. [File Override System](#file-override-system)
7. [Themes](#themes)
8. [External Media Directory](#external-media-directory)
9. [Adding a Custom InformationService](#adding-a-custom-informationservice)
10. [caUtils CLI Reference](#cautils-cli-reference)
    - [Convenience alias](#convenience-alias)
    - [Passing files into the container](#passing-files-into-the-container)
    - [Command reference](#command-reference)
11. [Database Operations](#database-operations)
    - [Backup](#backup)
    - [Importing a dump](#importing-a-dump)
    - [Restore from backup](#restore-from-backup)
    - [Drop and recreate the database](#drop-and-recreate-the-database)
12. [Runtime Data Directories](#runtime-data-directories)
13. [Batch Media Import](#batch-media-import)
14. [Umami Analytics (opt-in)](#umami-analytics-opt-in)
15. [Production Deployment](#production-deployment)
    - [External host nginx (recommended)](#external-host-nginx-recommended)
    - [SSL inside Docker with Certbot](#ssl-inside-docker-with-certbot)
    - [Production hardening checklist](#production-hardening-checklist)
16. [Maintenance](#maintenance)
17. [Troubleshooting](#troubleshooting)

---

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│  Host / VM                                              │
│                                                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │  Docker network: cacompose_default              │   │
│  │                                                 │   │
│  │  ┌──────────┐   /backend/ ┌────────────────┐   │   │
│  │  │          │────────────▶│  providence    │   │   │
│  │  │  nginx   │             │  (PHP+Apache)  │   │   │
│  │  │ :80/:443 │   /        ┌┴───────────────┐│   │   │
│  │  │          │────────────▶│  pawtucket2   ││   │   │
│  │  └──────────┘             │  (PHP+Apache) ││   │   │
│  │        │                  └───────────────┘│   │   │
│  │        │                        │           │   │   │
│  │        │                  ┌─────▼──────┐   │   │   │
│  │        │                  │   mysql    │   │   │   │
│  │        │                  │   redis    │   │   │   │
│  │        │                  └────────────┘   │   │   │
│  └────────┼────────────────────────────────────┘   │   │
│           │                                         │   │
│  :80 → redirect to HTTPS   :443 → HTTPS (dev cert) │   │
└─────────────────────────────────────────────────────────┘

Shared host path (MEDIA_PATH):
  ./media  →  /var/www/providence/media  (write)
          →  /var/www/pawtucket2/media  (read)
```

| URL | Service |
|-----|---------|
| `https://127.0.0.1/backend/` | Providence — cataloguing admin |
| `https://127.0.0.1/backend/install/` | Providence — web installer (first run) |
| `https://127.0.0.1/` | Pawtucket2 — public catalog |
| `https://127.0.0.1/install/` | Pawtucket2 — web installer (first run) |

Both apps share the **same MySQL database** and the **same media directory**.
Providence writes media; Pawtucket2 reads it.

---

## Quick Start

```bash
# 1. Clone this repo
git clone <this-repo> cacompose && cd cacompose

# 2. Set credentials — edit before proceeding
$EDITOR .env

# 3. Generate a self-signed TLS certificate for local HTTPS
./generate-dev-certs.sh

# 4. Create the media directory (or set MEDIA_PATH to an absolute path in .env)
mkdir -p ./media

# 5. Build and start  (first build takes 20-40 min — Composer installs for both apps)
docker compose up --build -d

# 6. Tail logs while starting up
docker compose logs -f
```

Then run the web installers (see [First-time Installation](#first-time-installation)).

---

## Configuration Reference

All configuration lives in `.env`. Every value is injected into the containers as an environment variable and read by `setup.php` via `getenv()`.

### Database

| Variable | Default | Description |
|----------|---------|-------------|
| `MYSQL_ROOT_PASSWORD` | — | MySQL root password (used only by the `db` container) |
| `CA_DB_HOST` | `db` | Database hostname (the Docker service name) |
| `CA_DB_PORT` | `3306` | Database port |
| `CA_DB_USER` | — | Application database user |
| `CA_DB_PASSWORD` | — | Application database password |
| `CA_DB_DATABASE` | — | Database name |

### Application

| Variable | Default | Description |
|----------|---------|-------------|
| `CA_APP_DISPLAY_NAME` | `My CollectiveAccess` | Human-readable site name |
| `CA_ADMIN_EMAIL` | `admin@example.com` | Admin contact address |
| `CA_DEFAULT_LOCALE` | `en_US` | Default UI locale |
| `TZ` | `UTC` | PHP / system timezone (e.g. `Europe/Vienna`) |

### Cache (Redis)

| Variable | Default | Description |
|----------|---------|-------------|
| `CA_CACHE_BACKEND` | `redis` | `redis`, `file`, `memcached`, or `apc` |
| `CA_REDIS_HOST` | `redis` | Redis hostname |
| `CA_REDIS_PORT` | `6379` | Redis port |
| `CA_PROVIDENCE_REDIS_DB` | `0` | Redis DB index for Providence |
| `CA_PAWTUCKET2_REDIS_DB` | `1` | Redis DB index for Pawtucket2 (separate namespace) |

### Media

| Variable | Default | Description |
|----------|---------|-------------|
| `MEDIA_PATH` | `./media` | **Required.** Host path mounted as the media directory in both containers. Must be an absolute path or relative to `docker-compose.yml`. See [External Media Directory](#external-media-directory). |

### Pawtucket2

| Variable | Default | Description |
|----------|---------|-------------|
| `CA_PAWTUCKET2_THEME` | `default` | Active theme folder name under `themes/` |

### Build

| Variable | Default | Description |
|----------|---------|-------------|
| `CA_PROVIDENCE_VERSION` | `master` | Git branch or tag to build Providence from |
| `CA_PAWTUCKET2_VERSION` | `master` | Git branch or tag to build Pawtucket2 from |

### Optional (not set by default)

| Variable | Description |
|----------|-------------|
| `CA_GOOGLE_MAPS_KEY` | Google Maps API key |
| `CA_GOOGLE_RECAPTCHA_KEY` | reCAPTCHA v2 site key (Pawtucket2 contact forms) |
| `CA_GOOGLE_RECAPTCHA_SECRET_KEY` | reCAPTCHA v2 secret key |
| `CA_STACKTRACE_ON_EXCEPTION` | Set to `1` during development for PHP stack traces |

---

## Development HTTPS

The default configuration serves both apps over HTTPS. A self-signed certificate is required before starting the stack.

### Generating the certificate

```bash
./generate-dev-certs.sh
```

This creates `nginx/certs/dev.crt` and `nginx/certs/dev.key` using the local `openssl` binary (valid 825 days, with SAN for `127.0.0.1` and `localhost`). The script restarts nginx automatically if the stack is already running.

- **Port 443** — HTTPS, main entry point
- **Port 80** — HTTP, immediately redirects to HTTPS

### Trusting the certificate (removes the browser warning)

Without trusting the certificate, browsers will show a security warning. You can click through it, or trust it permanently:

**macOS** — works for Chrome, Safari, and `curl`:
```bash
sudo security add-trusted-cert -d -r trustRoot \
  -k /Library/Keychains/System.keychain \
  nginx/certs/dev.crt
```

**Firefox** (any OS): Settings → Privacy & Security → View Certificates → Authorities → Import → select `nginx/certs/dev.crt` → check "Trust this CA to identify websites".

**Linux** (system-wide):
```bash
sudo cp nginx/certs/dev.crt /usr/local/share/ca-certificates/cacompose.crt
sudo update-ca-certificates
```

### Renewing

The certificate is valid for 825 days. Re-run `./generate-dev-certs.sh` to renew. If you previously trusted it in your OS keychain, you may need to remove the old entry first and re-trust the new one.

### Reverting to plain HTTP

If you prefer to skip TLS locally (e.g. for automated testing), replace `nginx/conf.d/default.conf` with an HTTP-only config and remove the `:443` port binding and `./nginx/certs` volume from the nginx service in `docker-compose.yml`. See the [External host nginx](#external-host-nginx-recommended) section for an example HTTP-only nginx config.

---

## First-time Installation

CollectiveAccess ships without a pre-built database schema. You run the web installer once after starting the containers.

### Step 1 — Install Providence

Visit **`https://127.0.0.1/backend/install/`** (or your domain).

The installer will:
- Create all database tables
- Create the initial administrator account
- Ask you to choose a starter profile (e.g. `default`, `museum`, `library`)

> **Tip:** `__CA_ALLOW_INSTALLER_TO_OVERWRITE_EXISTING_INSTALLS__` is `false` by default.
> The installer will refuse to run a second time unless you change this — a safety net against accidental data loss.

### Step 2 — Install Pawtucket2

Visit **`https://127.0.0.1/install/`**.

Pawtucket2 shares the same database; the installer only performs a lightweight check and configuration step. If Providence has already been installed, Pawtucket2 will detect the existing schema.

### Step 3 — Verify

- Admin backend: `https://127.0.0.1/backend/`
- Public catalog: `https://127.0.0.1/`

---

## File Override System

The override system lets you replace or add any file inside either container without modifying the Docker image or rebuilding.

### How it works

On every container start, the entrypoint script runs:

```bash
cp -r /overrides/{app}/. /var/www/{app}/
```

Files present in `overrides/` are copied over the corresponding paths in the app directory. New files (e.g. a theme folder) are added; existing files are replaced.

### Directory structure

```
overrides/
├── providence/          # Mirrors /var/www/providence/
│   └── app/
│       └── conf/
│           └── app.conf      ← replaces the built-in app.conf
└── pawtucket2/          # Mirrors /var/www/pawtucket2/
    ├── app/
    │   └── conf/
    │       └── app.conf      ← replaces the built-in app.conf
    └── themes/
        └── mytheme/          ← adds a new theme (see Themes section)
```

### Common override examples

**Replace a configuration file:**
```bash
# Get a copy of the original from the running container
docker compose cp providence:/var/www/providence/app/conf/app.conf \
    overrides/providence/app/conf/app.conf

# Edit it, then restart to apply
$EDITOR overrides/providence/app/conf/app.conf
docker compose restart providence
```

**Add a custom authentication adapter:**
```bash
# Place in the correct plugin directory — no rebuild needed
overrides/providence/app/lib/Auth/Adapters/MyLDAPAdapter.php
```

**Override setup.php entirely** (bypasses the getenv() template):
```bash
# Place a complete setup.php — it replaces the generated one
overrides/providence/setup.php
```

### Applying overrides after change

Overrides are applied at container **start**. A restart is sufficient (no rebuild needed):

```bash
docker compose restart providence
# or
docker compose restart pawtucket2
```

Verify the copy happened:
```bash
docker compose logs providence 2>&1 | grep -i override
# Should print: [entrypoint] Applying Providence overrides from /overrides/providence …
```

---

## Themes

Pawtucket2 ships with a `default` theme. Custom themes live in `themes/<theme-name>/`.

### Adding a custom theme

1. Copy the default theme from a running container as a starting point:
   ```bash
   docker compose cp pawtucket2:/var/www/pawtucket2/themes/default ./mytheme-base
   mkdir -p overrides/pawtucket2/themes/mytheme
   cp -r ./mytheme-base/. overrides/pawtucket2/themes/mytheme/
   ```

2. The theme directory structure:
   ```
   overrides/pawtucket2/themes/mytheme/
   ├── conf/
   │   └── theme.conf        ← theme metadata
   ├── views/                ← Smarty templates (.tpl files)
   │   ├── Browse/
   │   ├── Detail/
   │   └── ...
   └── assets/
       ├── css/
       ├── js/
       └── img/
   ```

3. Activate the theme in `.env`:
   ```dotenv
   CA_PAWTUCKET2_THEME=mytheme
   ```

4. Restart Pawtucket2:
   ```bash
   docker compose restart pawtucket2
   ```

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

## Adding a Custom InformationService

CollectiveAccess **InformationServices** are plugins that let fields in Providence look up data from external authorities — Getty ULAN, VIAF, GeoNames, a custom REST API, etc. The looked-up data can auto-populate fields or link records to external URIs.

### Plugin file

Create the PHP class in your overrides directory:

```
overrides/providence/app/lib/Plugins/InformationService/
└── WLPlugInformationService_MyService.php
```

The filename and class name must follow the pattern `WLPlugInformationService_<Name>`.

**Minimal implementation:**

```php
<?php
/**
 * Custom InformationService that queries an internal REST API.
 * File: WLPlugInformationService_MyService.php
 */

require_once(__CA_LIB_DIR__ . '/Plugins/InformationService/BaseInformationServicePlugin.php');
require_once(__CA_LIB_DIR__ . '/Plugins/IWLPlugInformationService.php');

class WLPlugInformationService_MyService
    extends BaseInformationServicePlugin
    implements IWLPlugInformationService
{
    /** Settings exposed in the Providence UI for this service */
    static $s_settings = [];

    public function __construct($settings, $type) {
        parent::__construct($settings, $type);
    }

    /**
     * Human-readable name shown in the Providence UI.
     */
    public function getDisplayName(): string {
        return 'My Custom Authority Service';
    }

    /**
     * Perform a keyword lookup.
     *
     * @param  BaseModel  $t_instance  The record being edited
     * @param  array      $settings    Plugin settings (from $s_settings)
     * @param  string     $query       The search string typed by the user
     * @param  array|null $pa_data     Extra context (optional)
     * @return array  List of results: [['label'=>..., 'url'=>..., 'idno'=>...], ...]
     */
    public function lookup($t_instance, $settings, $query, $pa_data = null): array {
        $results = [];

        $url = 'https://api.example.com/authorities?q=' . urlencode($query);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $raw = curl_exec($ch);
        curl_close($ch);

        if (!$raw) { return $results; }

        $data = json_decode($raw, true);
        foreach (($data['items'] ?? []) as $item) {
            $results[] = [
                'label' => $item['preferred_label'],
                'url'   => $item['uri'],
                'idno'  => $item['id'],
                // 'description' => $item['note'],
            ];
        }

        return $results;
    }

    /**
     * Return extended information for a specific URI (shown when user
     * hovers over or selects a result).
     *
     * @return array  ['display' => '<html snippet>']
     */
    public function getExtendedInformation($t_instance, $settings, $url): array {
        $ch = curl_init($url . '/json');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $raw = curl_exec($ch);
        curl_close($ch);

        $item = json_decode($raw, true) ?? [];
        $html = '<p><strong>' . htmlspecialchars($item['preferred_label'] ?? '') . '</strong></p>';
        if (!empty($item['note'])) {
            $html .= '<p>' . htmlspecialchars($item['note']) . '</p>';
        }

        return ['display' => $html];
    }
}
```

### Registering the service

Providence's `information_service.conf` maps **attribute types** to the list of available services. You need to add your plugin to that list.

1. Copy the original config from a running container:
   ```bash
   docker compose cp \
     providence:/var/www/providence/app/conf/information_service.conf \
     overrides/providence/app/conf/information_service.conf
   ```

2. Edit the copy and add your service name to the relevant type block:

   ```conf
   # overrides/providence/app/conf/information_service.conf

   ca_attribute_values_informationservice = {
       Entities = {
           sources = {
               ULAN       = { ... },
               VIAF       = { ... },
               MyService  = {
                   displayName = My Custom Authority Service,
                   url         = https://api.example.com,
               }
           }
       }
   }
   ```

   Consult the [CA documentation on InformationServices](https://docs.collectiveaccess.org/wiki/Information_Services) for the full list of attribute types and configuration keys.

3. Restart Providence to apply:
   ```bash
   docker compose restart providence
   ```

The service will now appear in the drop-down on any attribute field of the matching type when editing records in Providence.

---

## caUtils CLI Reference

`caUtils` is CollectiveAccess's built-in command-line tool for importing data, reindexing, managing users, processing media, and more. It lives at `support/bin/caUtils` inside both the Providence and Pawtucket2 containers, but is most useful in Providence where the full data model and admin privileges live.

### Convenience alias

Typing the full path every time is tedious. Add a shell alias for the current project:

```bash
# Paste into your shell session, or add to ~/.zshrc / ~/.bashrc
alias cautils='docker compose exec providence php /var/www/providence/support/bin/caUtils'
```

Then all examples below can be shortened, e.g. `cautils help`.

> The alias only works when your working directory is the project root (where `docker-compose.yml` lives), because `docker compose` resolves the project from the current directory.

### Passing files into the container

Many caUtils commands read from a file. Two patterns work:

**1. Pipe via stdin** — use `-T` to disable TTY allocation, then redirect with `<`:

```bash
docker compose exec -T providence \
  php /var/www/providence/support/bin/caUtils import-data \
    --format=XLSX --target=ca_objects --mapping=my_mapping \
  < /path/on/host/data.xlsx
```

**2. Copy the file into the container first** — better for large files or when you want to keep the file around:

```bash
docker compose cp /path/on/host/data.xlsx providence:/tmp/data.xlsx

docker compose exec providence \
  php /var/www/providence/support/bin/caUtils import-data \
    --format=XLSX --target=ca_objects --mapping=my_mapping \
    --source=/tmp/data.xlsx

# Clean up afterwards
docker compose exec providence rm /tmp/data.xlsx
```

### Command reference

#### Getting help

```bash
# List every available command with a one-line description
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils help

# Full help for a specific command (options, flags, examples)
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils help import-data
```

#### Search index

```bash
# Rebuild the entire search index (run after bulk imports or data migrations)
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils rebuild-search-index

# Rebuild index for a single table only
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils rebuild-search-index \
    --table=ca_objects
```

#### Media processing

```bash
# Process the background task queue (media transcoding, derivative generation, etc.)
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils process-task-queue

# Check media files for missing or broken derivatives
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils check-media \
    --table=ca_objects

# Regenerate all media derivatives (thumbnails, previews) for a table
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils reload-object-checksums \
    --table=ca_objects --regenerate-derivatives
```

#### Importing data

```bash
# Import from an Excel file
docker compose exec -T providence \
  php /var/www/providence/support/bin/caUtils import-data \
    --format=XLSX \
    --target=ca_objects \
    --mapping=my_mapping_code \
  < /path/to/data.xlsx

# Import from a CSV file
docker compose exec -T providence \
  php /var/www/providence/support/bin/caUtils import-data \
    --format=CSV \
    --target=ca_objects \
    --mapping=my_mapping_code \
  < /path/to/data.csv

# Import MARC records
docker compose exec -T providence \
  php /var/www/providence/support/bin/caUtils import-data \
    --format=MARC21 \
    --target=ca_objects \
    --mapping=my_mapping_code \
  < /path/to/records.mrc

# Import with verbose output (useful for debugging a mapping)
docker compose exec -T providence \
  php /var/www/providence/support/bin/caUtils import-data \
    --format=XLSX \
    --target=ca_objects \
    --mapping=my_mapping_code \
    --log-level=DEBUG \
  < /path/to/data.xlsx
```

Supported `--format` values include: `XLSX`, `XLS`, `CSV`, `TAB`, `MARC21`, `MARCXML`, `OAI_DC`, `RDF`, `JSON`, `CollectiveAccessXML`, and more. Run `cautils help import-data` for the complete list.

#### Exporting data

```bash
# Export all objects to CSV (file written inside the container)
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils export-data \
    --target=ca_objects \
    --format=CSV \
    --filename=/tmp/objects-export.csv

# Copy the result out to the host
docker compose cp providence:/tmp/objects-export.csv ./objects-export.csv
```

#### Searching records

```bash
# Search for records and print a summary
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils find \
    --table=ca_objects \
    --search="venice"

# Find a specific record by ID
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils find \
    --table=ca_objects \
    --id=42
```

#### User management

```bash
# Create a new administrator account
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils create-login \
    --username=newuser \
    --password=s3cret \
    --email=newuser@example.com \
    --fname=Jane \
    --lname=Doe \
    --roles=administrator

# Reset a forgotten password
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils reset-password \
    --username=admin \
    --password=newpassword
```

#### Database / data integrity

```bash
# Check database structure and report problems
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils repair-database

# Find and report duplicate labels
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils check-for-duplicate-labels \
    --table=ca_objects

# Permanently remove records that were soft-deleted
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils remove-deleted-records \
    --table=ca_objects
```

---

## Database Operations

### Backup

```bash
# Create a timestamped, gzip-compressed dump
docker compose exec db \
  mysqldump \
    -uroot -p"${MYSQL_ROOT_PASSWORD}" \
    --single-transaction \
    --routines \
    --triggers \
    "${CA_DB_DATABASE}" \
  | gzip > "backup-$(date +%Y%m%d-%H%M).sql.gz"
```

> `--single-transaction` takes a consistent snapshot without locking tables — safe for live systems.

### Importing a dump

Use this when migrating from another server, restoring a colleague's dataset, or seeding a fresh install from an existing database.

#### Into an existing (empty) database

```bash
# Plain .sql file
docker compose exec -T db \
  mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" "${CA_DB_DATABASE}" \
  < /path/to/dump.sql

# Gzip-compressed file
gunzip -c /path/to/dump.sql.gz \
  | docker compose exec -T db \
    mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" "${CA_DB_DATABASE}"
```

#### Into a clean database (drop → recreate → import)

If the target database already has data and you want to replace it entirely:

```bash
# 1. Drop and recreate the database with the correct character set
docker compose exec db \
  mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" -e "
    DROP DATABASE IF EXISTS \`${CA_DB_DATABASE}\`;
    CREATE DATABASE \`${CA_DB_DATABASE}\`
      CHARACTER SET utf8mb4
      COLLATE utf8mb4_unicode_ci;
    GRANT ALL PRIVILEGES ON \`${CA_DB_DATABASE}\`.* TO '${CA_DB_USER}'@'%';
    FLUSH PRIVILEGES;
  "

# 2. Import the dump
gunzip -c /path/to/dump.sql.gz \
  | docker compose exec -T db \
    mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" "${CA_DB_DATABASE}"
```

#### Large files — copy into the container first

Piping very large dumps (several GB) through stdin can be slow or prone to disconnection. Copying the file directly into the container is more reliable:

```bash
# Copy the dump file into the db container
docker compose cp /path/to/dump.sql.gz db:/tmp/dump.sql.gz

# Import inside the container (no pipe overhead)
docker compose exec db \
  bash -c "gunzip -c /tmp/dump.sql.gz \
    | mysql -uroot -p\"${MYSQL_ROOT_PASSWORD}\" \"${CA_DB_DATABASE}\""

# Clean up
docker compose exec db rm /tmp/dump.sql.gz
```

#### With progress display

If you have [`pv`](https://www.ivarch.com/programs/pv.shtml) installed on the host, you can watch import progress:

```bash
# pv shows a progress bar, estimated time remaining, and throughput
pv /path/to/dump.sql \
  | docker compose exec -T db \
    mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" "${CA_DB_DATABASE}"

# Compressed
pv /path/to/dump.sql.gz | gunzip \
  | docker compose exec -T db \
    mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" "${CA_DB_DATABASE}"
```

#### After importing — rebuild indexes

CollectiveAccess's full-text search index is stored separately from the MySQL data. After importing a dump you must rebuild it:

```bash
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils rebuild-search-index
```

### Restore from backup

Restoring a backup made with the `mysqldump` command above is the same as importing a dump:

```bash
gunzip -c backup-20240101-1200.sql.gz \
  | docker compose exec -T db \
    mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" "${CA_DB_DATABASE}"

# Rebuild search index after restore
docker compose exec providence \
  php /var/www/providence/support/bin/caUtils rebuild-search-index
```

### Drop and recreate the database

Useful for a completely fresh install after experimenting:

```bash
docker compose exec db \
  mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" -e "
    DROP DATABASE IF EXISTS \`${CA_DB_DATABASE}\`;
    CREATE DATABASE \`${CA_DB_DATABASE}\`
      CHARACTER SET utf8mb4
      COLLATE utf8mb4_unicode_ci;
    GRANT ALL PRIVILEGES ON \`${CA_DB_DATABASE}\`.* TO '${CA_DB_USER}'@'%';
    FLUSH PRIVILEGES;
  "
```

Then run the web installer again at `https://127.0.0.1/backend/install/`.

> Set `__CA_ALLOW_INSTALLER_TO_OVERWRITE_EXISTING_INSTALLS__ = true` in `overrides/providence/setup.php` if the installer refuses to run because it detects an existing installation. **Reset it to `false` afterwards.**

---

## Production Deployment

The default configuration uses a self-signed certificate suited for local development. In production you need a trusted TLS certificate and public port bindings. Two approaches are described below.

### External host nginx (recommended)

Your existing system nginx (or a VM-level nginx) terminates TLS with a real certificate, then proxies over plain HTTP to the Docker stack. This is the most common setup for VPS / bare-metal deployments.

#### Step 1 — Switch Docker nginx to HTTP-only

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

#### Step 2 — Expose only an internal port

In `docker-compose.yml`, change the nginx `ports:` so it only listens on localhost (the host nginx will proxy to it) and remove the cert volume:

```yaml
nginx:
  ports:
    - "127.0.0.1:8080:80"    # only reachable from the host itself
  volumes:
    - ./nginx/conf.d/default.conf:/etc/nginx/conf.d/default.conf:ro
    # no certs volume needed
```

#### Step 3 — Host nginx virtual host with TLS

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

#### Step 4 — Obtain a TLS certificate

```bash
sudo certbot --nginx -d catalog.example.com
```

---

### SSL inside Docker with Certbot

If you prefer to keep everything inside the Docker stack (no host nginx), replace the self-signed certificate with a Let's Encrypt certificate.

#### Add Certbot to `docker-compose.yml`

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

#### Updated nginx config

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

#### Certificate renewal

```bash
# crontab -e
0 3 * * * docker compose -f /path/to/cacompose/docker-compose.yml \
          run --rm certbot renew --quiet \
          && docker compose -f /path/to/cacompose/docker-compose.yml \
          exec nginx nginx -s reload
```

---

### Production hardening checklist

```
[ ] Change ALL default passwords in .env
[ ] Set MEDIA_PATH to an absolute path with a real backup strategy
[ ] Set TZ to your local timezone
[ ] CA_ADMIN_EMAIL to a real monitored address
[ ] Bind Docker nginx to 127.0.0.1 (not 0.0.0.0) when using host nginx
[ ] CA_STACKTRACE_ON_EXCEPTION must be empty/false in production
[ ] Ensure __CA_ALLOW_INSTALLER_TO_OVERWRITE_EXISTING_INSTALLS__ = false
[ ] Lock down MySQL: CA_DB_USER should not have SUPER or FILE privileges
[ ] Firewall: only allow 80/443 externally; block 3306/6379
[ ] Set up automated database backups (see Maintenance section)
[ ] Configure Docker log rotation in /etc/docker/daemon.json:
      { "log-driver": "json-file",
        "log-opts": { "max-size": "50m", "max-file": "5" } }
[ ] Pin image versions in docker-compose.yml for reproducible builds:
      image: mysql:8.0.36  /  redis:7.2-alpine
[ ] Pin CA_PROVIDENCE_VERSION and CA_PAWTUCKET2_VERSION to a release tag
      (e.g. 2.0.2) instead of master
```

---

## Maintenance

### Rebuild after a CA version update

```bash
# Update version in .env, then:
docker compose build --no-cache providence pawtucket2
docker compose up -d
```

### Database backup and restore

See the dedicated [Database Operations](#database-operations) section below for full backup, restore, and dump-import instructions.

### Clear the Redis cache

```bash
# Clear Providence cache (DB 0)
docker compose exec redis redis-cli -n 0 FLUSHDB

# Clear Pawtucket2 cache (DB 1)
docker compose exec redis redis-cli -n 1 FLUSHDB
```

### caUtils CLI

See the dedicated [caUtils CLI Reference](#cautils-cli-reference) section for the full command reference and examples.

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
To suppress the browser warning permanently, trust the certificate — see [Development HTTPS](#development-https).

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

---

## Batch Media Import

Providence's **media importer** scans a staging directory for files to import in bulk. The default location inside the container is `/var/www/providence/import/`.

### Configuration

Set `IMPORT_PATH` in `.env` to any host directory you want to use as the staging area:

```dotenv
IMPORT_PATH=./import          # relative to docker-compose.yml (default)
# or an absolute path:
IMPORT_PATH=/mnt/nas/ca-import
```

The directory is bind-mounted read-write into the Providence container at `/var/www/providence/import/`. No `app.conf` override is needed — CA's default value for `batch_media_import_root_directory` already points to this path.

### Workflow

1. Drop files into `$IMPORT_PATH` (or the `./import/` folder):
   ```bash
   cp /path/to/images/*.jpg ./import/
   ```

2. In the Providence admin UI: **Media → Media importer → Import from directory**.

3. The importer lists everything it finds under the staging directory. Select files and run the import job.

### Tips

- Sub-directories are supported — organize files into folders before importing.
- Files are **not deleted** after import; remove them manually once done.
- The import directory is mounted in Providence only (Pawtucket2 has no importer).

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
