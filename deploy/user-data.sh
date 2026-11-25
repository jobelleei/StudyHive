#!/bin/bash
# =====================================================================
# StudyHive — EC2 launch script (user data)
# OS: Amazon Linux 2023

# On every new instance it:
#   1. installs Apache, PHP and the MariaDB client
#   2. clones the StudyHive repo from GitHub into /var/www/html
#   3. reads the DB settings from SSM Parameter Store (no passwords here)
#   4. writes /etc/studyhive/config.php (outside the web root)
#   5. creates the database tables if they don't exist yet
#
# Required SSM parameters (Systems Manager -> Parameter Store):
#   /studyhive/db/host      String        RDS endpoint
#   /studyhive/db/name      String        studyhive_db
#   /studyhive/db/user      String        admin
#   /studyhive/db/password  SecureString  RDS master password
#
# Required on the instance role (studyhive-ec2-role):
#   ssm:GetParameter on arn:aws:ssm:<region>:<account>:parameter/studyhive/*
#
# =====================================================================
set -euo pipefail
exec > >(tee -a /var/log/studyhive-setup.log) 2>&1

REPO_URL="https://github.com/jobelleei/StudyHive.git"
BRANCH="main"
WEB_ROOT="/var/www/html"

# ---- 1. Packages -----------------------------------------------------
dnf -y update
dnf -y install httpd php php-mysqlnd php-pdo php-mbstring php-xml php-fpm git mariadb105

# ---- 2. Application code --------------------------------------------
rm -rf "${WEB_ROOT:?}"/*
git clone --depth 1 --branch "$BRANCH" "$REPO_URL" /tmp/studyhive
cp -r /tmp/studyhive/. "$WEB_ROOT"/
rm -rf "$WEB_ROOT/.git" /tmp/studyhive
chown -R apache:apache "$WEB_ROOT"

# ---- 3. Database settings from SSM Parameter Store -------------------
TOKEN=$(curl -s -X PUT "http://169.254.169.254/latest/api/token" \
  -H "X-aws-ec2-metadata-token-ttl-seconds: 300")
REGION=$(curl -s -H "X-aws-ec2-metadata-token: $TOKEN" \
  http://169.254.169.254/latest/meta-data/placement/region)

get_param() {
  aws ssm get-parameter --region "$REGION" --name "$1" \
    --with-decryption --query "Parameter.Value" --output text
}

DB_HOST=$(get_param /studyhive/db/host)
DB_NAME=$(get_param /studyhive/db/name)
DB_USER=$(get_param /studyhive/db/user)
DB_PASS=$(get_param /studyhive/db/password)

# ---- 4. Server-only config file (read by config/database.php) --------
mkdir -p /etc/studyhive

# RDS requires TLS (require_secure_transport=ON). Download the AWS RDS
# CA bundle so the app and the mysql client can verify the database.
SSL_CA="/etc/studyhive/rds-ca.pem"
curl -fsSL -o "$SSL_CA" https://truststore.pki.rds.amazonaws.com/global/global-bundle.pem
chmod 644 "$SSL_CA"

php -r '
  $c = ["host" => $argv[1], "dbname" => $argv[2], "username" => $argv[3], "password" => $argv[4], "ssl_ca" => $argv[5]];
  file_put_contents("/etc/studyhive/config.php", "<?php\nreturn " . var_export($c, true) . ";\n");
' "$DB_HOST" "$DB_NAME" "$DB_USER" "$DB_PASS" "$SSL_CA"
chown root:apache /etc/studyhive/config.php
chmod 640 /etc/studyhive/config.php

# ---- 5. Create tables if missing (schema is idempotent) --------------
MYSQL_PWD="$DB_PASS" mysql -h "$DB_HOST" -u "$DB_USER" \
  --ssl-ca="$SSL_CA" --ssl-verify-server-cert "$DB_NAME" \
  < "$WEB_ROOT/database/schema.sql"

# Don't serve the schema or deploy scripts over HTTP
rm -rf "$WEB_ROOT/database" "$WEB_ROOT/deploy"

# ---- 6. Start the web server ------------------------------------------
systemctl enable --now php-fpm
systemctl enable --now httpd

echo "StudyHive setup finished at $(date)"
