#!/bin/bash
# Idempotent first-run setup. Runs in the background while Apache starts.
# Free Render instances have no shell and no disk, so everything must happen automatically here.
set -u
cd /var/www/html || exit 1
log() { echo "[cc-bootstrap] $*"; }
WP="wp --allow-root --path=/var/www/html"

# 1. wait for the entrypoint to write wp-config.php
for _ in $(seq 1 60); do [ -f wp-config.php ] && break; sleep 2; done
[ -f wp-config.php ] || { log "wp-config.php never appeared"; exit 1; }

# 2. wait for MySQL (TLS unless CC_DB_SSL=0)
HOSTPORT="${WORDPRESS_DB_HOST:-localhost}"
DBHOST="${HOSTPORT%%:*}"; DBPORT=3306
[[ "$HOSTPORT" == *:* ]] && DBPORT="${HOSTPORT##*:}"
export DBHOST DBPORT
ok=0
for i in $(seq 1 40); do
  if php -r '
    mysqli_report(MYSQLI_REPORT_OFF);
    $m = mysqli_init();
    $flags = getenv("CC_DB_SSL") === "0" ? 0 : MYSQLI_CLIENT_SSL;
    $r = @$m->real_connect(getenv("DBHOST"), getenv("WORDPRESS_DB_USER"), getenv("WORDPRESS_DB_PASSWORD"), getenv("WORDPRESS_DB_NAME"), (int)getenv("DBPORT"), null, $flags);
    exit($r ? 0 : 1);'; then ok=1; break; fi
  log "database not reachable yet (attempt $i)"; sleep 5
done
[ "$ok" = 1 ] || { log "database unreachable, giving up. Check firewall, credentials and TLS."; exit 1; }

# 3. install WordPress once
if ! $WP core is-installed 2>/dev/null; then
  : "${WP_ADMIN_PASSWORD:?WP_ADMIN_PASSWORD must be set}"
  log "installing WordPress at $CC_SITE_URL"
  $WP core install --url="$CC_SITE_URL" --title="CareConnect (Demo)" \
    --admin_user="${WP_ADMIN_USER:-ccadmin}" --admin_password="$WP_ADMIN_PASSWORD" \
    --admin_email="${WP_ADMIN_EMAIL:-admin@example.test}" --skip-email || { log "core install failed"; exit 1; }
fi

# 4. theme, plugin, permalinks, safe defaults
$WP theme activate careconnect-headless
$WP plugin activate careconnect-services
$WP rewrite structure '/%postname%/' >/dev/null
$WP rewrite flush --hard >/dev/null 2>&1 || true
$WP option update blog_public 0 >/dev/null
$WP option update default_comment_status closed >/dev/null
$WP option update users_can_register 0 >/dev/null

# 5. demo page that hosts the [careconnect_services] shortcode (plugin requirement P4)
if [ -z "$($WP post list --post_type=page --name=wp-services-demo --format=ids)" ]; then
  $WP post create --post_type=page --post_status=publish --post_title="WP Services Demo" \
    --post_name=wp-services-demo --post_content='[careconnect_services]' >/dev/null
fi

# 6. demo users for reviewers (fictional addresses on the reserved .test TLD)
if [ -n "${CC_DEMO_PASSWORD:-}" ]; then
  $WP user get demo_editor    >/dev/null 2>&1 || $WP user create demo_editor    editor@example.test    --role=cc_editor    --user_pass="$CC_DEMO_PASSWORD"
  $WP user get demo_publisher >/dev/null 2>&1 || $WP user create demo_publisher publisher@example.test --role=cc_publisher --user_pass="$CC_DEMO_PASSWORD"
fi

# 7. seed fictional content (command is idempotent; implemented by the plugin)
$WP careconnect seed
log "bootstrap complete"
