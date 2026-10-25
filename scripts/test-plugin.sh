#!/usr/bin/env bash
# Destructive tests are restricted to the disposable compose test-db service.
set -euo pipefail
cd /plugin
composer install --no-interaction
# The generated installer prompts if a database already exists. Create only this
# isolated test database ourselves, then skip its interactive database step.
mysql --host=test-db --user=root --password=tests-only --execute='CREATE DATABASE IF NOT EXISTS careconnect_tests;'
bash bin/install-wp-tests.sh careconnect_tests root tests-only test-db:3306 6.9.4 true
composer phpcs
composer test
