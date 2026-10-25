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

DB=careconnect; USER_NAME=careconnect; PASSFILE=/root/.careconnect-db-pass

# 1. install MySQL only if missing
if ! command -v mysqld >/dev/null 2>&1; then
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -y && apt-get install -y mysql-server
fi
systemctl enable --now mysql

# 2. TLS required, listen on network, no DNS lookups (MySQL 8 auto-generates a self-signed server cert on first start)
cat > /etc/mysql/mysql.conf.d/zz-careconnect.cnf <<CNF
[mysqld]
bind-address = 0.0.0.0
require_secure_transport = ON
tls_version = TLSv1.2,TLSv1.3
skip_name_resolve = ON
CNF
systemctl restart mysql
mysql -N -e "SHOW VARIABLES LIKE 'have_ssl'" | grep -q YES || { echo "MySQL TLS is not enabled; stop."; exit 1; }

# 3. database + one user per allowed IP, TLS enforced per account
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
