#!/bin/bash
# Wrapper: start the bootstrap in the background, then hand over to the official WordPress entrypoint
# (which generates wp-config.php from WORDPRESS_* env vars and starts Apache).
set -e
# Render injects RENDER_EXTERNAL_URL (https://<service>.onrender.com). Use it unless CC_SITE_URL is set.
export CC_SITE_URL="${CC_SITE_URL:-${RENDER_EXTERNAL_URL:-http://localhost:8080}}"
/usr/local/bin/cc-bootstrap.sh &
exec docker-entrypoint.sh "$@"
