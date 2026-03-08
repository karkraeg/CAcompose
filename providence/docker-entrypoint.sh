#!/bin/bash
set -euo pipefail

APP_DIR=/var/www/providence
OVERRIDES_DIR=/overrides/providence

# ── Apply override files ───────────────────────────────────────────────────────
# Any file placed under overrides/providence/ (on the host) will be copied
# over the corresponding path inside the container.
#
# Examples:
#   overrides/providence/app/conf/app.conf    → replaces app/conf/app.conf
#   overrides/providence/setup.php            → replaces generated setup.php
#
if [ -d "$OVERRIDES_DIR" ] && [ -n "$(ls -A "$OVERRIDES_DIR" 2>/dev/null)" ]; then
    echo "[entrypoint] Applying Providence overrides from $OVERRIDES_DIR …"
    cp -r "$OVERRIDES_DIR/." "$APP_DIR/"
    chown -R www-data:www-data "$APP_DIR"
fi

# ── Ensure writable directories exist ─────────────────────────────────────────
mkdir -p "$APP_DIR/app/tmp" "$APP_DIR/app/log" "$APP_DIR/media" "$APP_DIR/import"
chown -R www-data:www-data "$APP_DIR/app/tmp" "$APP_DIR/app/log" "$APP_DIR/media" "$APP_DIR/import"
chmod -R 755 "$APP_DIR/app/tmp" "$APP_DIR/app/log" "$APP_DIR/media" "$APP_DIR/import"

echo "[entrypoint] Starting Apache …"
exec "$@"
