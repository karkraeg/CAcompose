# Getting Started

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

| URL                                  | Service                                |
|--------------------------------------|----------------------------------------|
| `https://127.0.0.1/backend/`         | Providence — cataloguing admin         |
| `https://127.0.0.1/backend/install/` | Providence — web installer (first run) |
| `https://127.0.0.1/`                 | Pawtucket2 — public catalog            |

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

Then run the web installers (see [First-time Installation](#first-time-installation) below).

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

If you prefer to skip TLS locally (e.g. for automated testing), replace `nginx/conf.d/default.conf` with an HTTP-only config and remove the `:443` port binding and `./nginx/certs` volume from the nginx service in `docker-compose.yml`. See the [Production Deployment](production.md#external-host-nginx-recommended) section for an example HTTP-only nginx config.

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
