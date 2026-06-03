#!/bin/bash
set -euo pipefail

APP_DIR=/var/www/pawtucket2
OVERRIDES_DIR=/overrides/pawtucket2

# ── Apply override files ───────────────────────────────────────────────────────
# Any file/folder placed under overrides/pawtucket2/ (on the host) will be
# merged into the corresponding path inside the container.
#
# Examples:
#   overrides/pawtucket2/app/conf/app.conf     → replaces app/conf/app.conf
#   overrides/pawtucket2/themes/mytheme/       → adds themes/mytheme/ (new theme)
#   overrides/pawtucket2/setup.php             → replaces generated setup.php
#
if [ -d "$OVERRIDES_DIR" ] && [ -n "$(ls -A "$OVERRIDES_DIR" 2>/dev/null)" ]; then
    echo "[entrypoint] Applying Pawtucket2 overrides from $OVERRIDES_DIR …"
    cp -r "$OVERRIDES_DIR/." "$APP_DIR/"
    # chown only the top-level items that were actually copied, not the entire app tree
    while IFS= read -r src; do
        rel="${src#$OVERRIDES_DIR/}"
        chown -R www-data:www-data "$APP_DIR/$rel"
    done < <(find "$OVERRIDES_DIR" -mindepth 1 -maxdepth 1)
fi

# ── Ensure writable directories exist ─────────────────────────────────────────
mkdir -p "$APP_DIR/app/tmp" "$APP_DIR/app/log" "$APP_DIR/media"
chown -R www-data:www-data "$APP_DIR/app/tmp" "$APP_DIR/app/log"
chmod -R 755 "$APP_DIR/app/tmp" "$APP_DIR/app/log"
# Non-recursive: bind-mount root may contain millions of files
chown www-data:www-data "$APP_DIR/media"
chmod 755 "$APP_DIR/media"

echo "[entrypoint] Starting Apache …"
exec "$@"
