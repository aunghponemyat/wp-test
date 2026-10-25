# CareConnect WordPress backend for Render (Docker runtime, free plan).
# Plugin + headless theme are baked into the image. Config is via env vars only.
# Official image tag family: https://hub.docker.com/_/wordpress  (pin a full tag, e.g. 6.x-php8.3-apache, after checking Docker Hub)
FROM wordpress:6-php8.3-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends less unzip \
 && rm -rf /var/lib/apt/lists/*

# WP-CLI (used only by the bootstrap script for install, activation and seeding)
RUN curl -fsSL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp \
 && chmod +x /usr/local/bin/wp \
 && wp --allow-root --info >/dev/null

# IMPORTANT: copy into /usr/src/wordpress, NOT /var/www/html.
# /var/www/html is a VOLUME in the base image and the entrypoint copies /usr/src/wordpress into it at start.
COPY wordpress/wp-content/plugins/careconnect-services /usr/src/wordpress/wp-content/plugins/careconnect-services
COPY wordpress/wp-content/themes/careconnect-headless  /usr/src/wordpress/wp-content/themes/careconnect-headless

# Production PHP settings (no uploads: demo media is non-persistent and upload UI is disabled by the plugin)
RUN { \
      echo 'expose_php=Off'; \
      echo 'display_errors=Off'; \
      echo 'log_errors=On'; \
      echo 'file_uploads=Off'; \
      echo 'memory_limit=128M'; \
    } > /usr/local/etc/php/conf.d/careconnect.ini \
 && { echo 'ServerTokens Prod'; echo 'ServerSignature Off'; } > /etc/apache2/conf-available/careconnect-hardening.conf \
 && a2enconf careconnect-hardening

COPY docker/cc-entrypoint.sh /usr/local/bin/cc-entrypoint.sh
COPY docker/cc-bootstrap.sh  /usr/local/bin/cc-bootstrap.sh
RUN chmod +x /usr/local/bin/cc-entrypoint.sh /usr/local/bin/cc-bootstrap.sh

EXPOSE 80
ENTRYPOINT ["cc-entrypoint.sh"]
CMD ["apache2-foreground"]
