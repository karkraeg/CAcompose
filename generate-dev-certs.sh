#!/bin/bash
# Generates a self-signed TLS certificate for local HTTPS development.
#
# Requires: openssl (comes with macOS, any Linux distro)
#
# Run once before first `docker compose up`, or any time you want to renew:
#
#   ./generate-dev-certs.sh
#
# To make your browser trust the certificate without a warning, see the
# "Trusting the certificate" section printed at the end.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
CERTS_DIR="$SCRIPT_DIR/nginx/certs"
CERT="$CERTS_DIR/dev.crt"
KEY="$CERTS_DIR/dev.key"

mkdir -p "$CERTS_DIR"

echo "Generating self-signed certificate …"

openssl req -x509 -nodes -days 825 -newkey rsa:2048 \
  -keyout "$KEY" \
  -out    "$CERT" \
  -subj   '/CN=localhost' \
  -addext 'subjectAltName=IP:127.0.0.1,DNS:localhost' \
  2>/dev/null

chmod 644 "$CERT"
chmod 600 "$KEY"

echo ""
echo "Certificate : $CERT"
echo "Private key : $KEY"
echo "Valid for   : 825 days"
echo ""

# ── Restart nginx if the stack is already running ─────────────────────────────
if docker compose ps nginx 2>/dev/null | grep -q "Up"; then
  echo "Stack is running — reloading nginx …"
  docker compose restart nginx
  echo ""
fi

# ── Browser trust instructions ────────────────────────────────────────────────
echo "┌─────────────────────────────────────────────────────────────────────┐"
echo "│  Trusting the certificate (removes the browser warning)            │"
echo "├─────────────────────────────────────────────────────────────────────┤"
echo "│                                                                     │"
echo "│  macOS (Keychain — works for Chrome, Safari, curl):                │"
echo "│    sudo security add-trusted-cert -d -r trustRoot \\               │"
echo "│      -k /Library/Keychains/System.keychain \\                      │"
echo "│      nginx/certs/dev.crt                                           │"
echo "│                                                                     │"
echo "│  Linux (system-wide):                                              │"
echo "│    sudo cp nginx/certs/dev.crt /usr/local/share/ca-certificates/  │"
echo "│    sudo update-ca-certificates                                      │"
echo "│                                                                     │"
echo "│  Firefox (any OS):                                                  │"
echo "│    Settings → Privacy & Security → View Certificates               │"
echo "│    → Authorities → Import → select dev.crt → trust for websites   │"
echo "│                                                                     │"
echo "│  After trusting: https://127.0.0.1/ and https://127.0.0.1/backend/│"
echo "└─────────────────────────────────────────────────────────────────────┘"
