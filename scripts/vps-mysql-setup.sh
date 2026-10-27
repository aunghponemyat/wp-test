#!/usr/bin/env bash
# Idempotent MySQL 8 setup for the CareConnect demo on an Ubuntu VPS (run as root via: ssh do-sg).
# Usage:  sudo bash vps-mysql-setup.sh "<render-outbound-ip-1> <render-outbound-ip-2> ..."
# Render outbound IPs for the Singapore region are listed in the Render Dashboard for the service
# (Connect menu -> Outbound) and in Render's docs. Never open 3306 to 0.0.0.0/0.
set -euo pipefail

[ "$(id -u)" -eq 0 ] || { echo "Run as root."; exit 1; }
IPS="${1:-}"; [ -n "$IPS" ] || { echo "Pass Render outbound IPs as one quoted argument."; exit 1; }
for ip in $IPS; do [[ "$ip" =~ ^[0-9]{1,3}(\.[0-9]{1,3}){3}(/[0-9]{1,2})?$ ]] || { echo "Bad IP/CIDR: $ip"; exit 1; }; done
. /etc/os-release; [ "$ID" = "ubuntu" ] || { echo "Ubuntu expected, found $ID. Adjust the script before running."; exit 1; }

# Package installation starts MySQL, so check headroom before invoking apt.
# This conservative guard is not a guarantee or a MySQL minimum requirement.
CC_AVAILABLE_MEMORY_KIB="$(awk '/^MemAvailable:/ {print $2}' /proc/meminfo)"
CC_FREE_SWAP_KIB="$(awk '/^SwapFree:/ {print $2}' /proc/meminfo)"
if (( CC_AVAILABLE_MEMORY_KIB + CC_FREE_SWAP_KIB < 524288 )); then
  echo "Less than 512 MiB of available RAM plus free swap. Free resources or add swap before installing MySQL."
  exit 1
fi

DB=careconnect; USER_NAME=careconnect; PASSFILE=/root/.careconnect-db-pass

# 1. install MySQL only if missing
if ! command -v mysqld >/dev/null 2>&1; then
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -y && apt-get install -y mysql-server
fi
systemctl enable --now mysql

# CIDR account hosts require MySQL 8.0.23 or newer. Check before changing config.
DB_SERVER_VERSION="$(mysql -N -e 'SELECT VERSION()')"
if [[ ! "$DB_SERVER_VERSION" =~ ^8\.([0-9]+)\.([0-9]+) ]]; then
  echo "MySQL 8.0.23 or newer in the 8.x series is required; found $DB_SERVER_VERSION."
  exit 1
fi
if (( 10#${BASH_REMATCH[1]} == 0 && 10#${BASH_REMATCH[2]} < 23 )); then
  echo "MySQL 8.0.23 or newer is required for CIDR account hosts; found $DB_SERVER_VERSION."
  exit 1
fi

# 2. TLS required, listen on network, no DNS lookups (MySQL 8 auto-generates a self-signed server cert on first start)
cat > /etc/mysql/mysql.conf.d/zz-careconnect.cnf <<CNF
[mysqld]
bind-address = 0.0.0.0
require_secure_transport = ON
tls_version = TLSv1.2,TLSv1.3
skip_name_resolve = ON
CNF
systemctl restart mysql
# have_ssl was removed in MySQL 8.4; inspect the active main TLS channel instead.
mysql -N -e "SELECT VALUE FROM performance_schema.tls_channel_status WHERE CHANNEL = 'mysql_main' AND PROPERTY = 'Enabled'" | grep -qi '^yes$' || { echo "MySQL TLS is not enabled; stop."; exit 1; }

# 3. database + one user per allowed IP/CIDR range, TLS enforced per account
if [ ! -s "$PASSFILE" ]; then umask 077; openssl rand -hex 24 > "$PASSFILE"; fi
chmod 600 "$PASSFILE"; PASS="$(cat "$PASSFILE")"
mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
SQL
for ip in $IPS; do
mysql <<SQL
CREATE USER IF NOT EXISTS '$USER_NAME'@'$ip' IDENTIFIED BY '$PASS' REQUIRE SSL;
ALTER USER '$USER_NAME'@'$ip' IDENTIFIED BY '$PASS' REQUIRE SSL;
GRANT ALL PRIVILEGES ON \`$DB\`.* TO '$USER_NAME'@'$ip';
SQL
done
mysql -e "FLUSH PRIVILEGES"

# 4. firewall: keep SSH reachable FIRST, then allow 3306 only from the listed IPs
command -v ufw >/dev/null || apt-get install -y ufw
ufw allow OpenSSH
for ip in $IPS; do ufw allow from "$ip" to any port 3306 proto tcp; done
ufw status | grep -q "Status: active" || ufw --force enable
ufw status verbose

echo
echo "Done. DB=$DB USER=$USER_NAME  password stored in $PASSFILE (root only, mode 600)."
echo "Set Render env: WORDPRESS_DB_HOST=<this-vps-public-ip>:3306  WORDPRESS_DB_PASSWORD=<contents of $PASSFILE>"
echo "If the VPS also has a provider cloud firewall (e.g. DigitalOcean), allow TCP 3306 from the same IPs there too."
