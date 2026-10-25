# Local test tooling only. The application Dockerfile remains as provided.
FROM wordpress:6.9.4-php8.3-apache@sha256:5d2c212561c4b5442ebc4d98933a9cbadcf3dee8888ed3fd9ed44667c27cc905
RUN apt-get update \
 && apt-get install -y --no-install-recommends git subversion default-mysql-client unzip \
 && rm -rf /var/lib/apt/lists/* \
 && printf '[client]\nskip-ssl\n' > /etc/mysql/conf.d/careconnect-tests.cnf
# Composer version was resolved using its stable installer during implementation.
RUN curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php \
 && php /tmp/composer-setup.php --version=2.10.3 --install-dir=/usr/local/bin --filename=composer \
 && rm /tmp/composer-setup.php
ENV COMPOSER_HOME=/tmp/composer
WORKDIR /plugin
