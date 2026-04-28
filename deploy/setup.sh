#!/bin/bash
set -e

echo ""
echo "╔═══════════════════════════════════════╗"
echo "║     Cardrona Hut — Server Setup       ║"
echo "╚═══════════════════════════════════════╝"
echo ""

# ── Prompt for config ────────────────────────────────────────────────────────
read -s -p "Set a database password: " DB_PASS
echo ""
echo ""

if [ -z "$DB_PASS" ]; then
  echo "Error: password is required."
  exit 1
fi

DB_ROOT=$(openssl rand -hex 16)
SERVER_IP=$(curl -s ifconfig.me)

echo "▶ Installing Git..."
apt-get update -qq && apt-get install -y -qq git > /dev/null

echo "▶ Opening firewall port 8080..."
ufw allow 8080/tcp > /dev/null 2>&1 || true

echo "▶ Cloning repository..."
mkdir -p /opt/cardrona-hut
if [ -d "/opt/cardrona-hut/.git" ]; then
  cd /opt/cardrona-hut && git pull origin main
else
  git clone https://github.com/Mckenzieandco-nz/cardrona-hut.git /opt/cardrona-hut
fi
cd /opt/cardrona-hut

echo "▶ Creating .env file..."
cat > /opt/cardrona-hut/.env <<EOF
DB_ROOT_PASSWORD=${DB_ROOT}
DB_PASSWORD=${DB_PASS}
EOF

echo "▶ Creating config.php..."
cat > /opt/cardrona-hut/config.php <<EOF
<?php
define('DB_HOST', 'cardrona-db');
define('DB_NAME', 'cardrona_hut');
define('DB_USER', 'cardrona_user');
define('DB_PASS', '${DB_PASS}');
define('SITE_NAME', 'Cardrona Hut');
define('BASE_URL', '');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_UPLOAD_MB', 10);
define('MAX_UPLOAD_SIZE', MAX_UPLOAD_MB * 1024 * 1024);
EOF

echo "▶ Building and starting containers..."
docker compose up -d --build

echo "▶ Waiting for database to be ready..."
sleep 20

echo ""
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║  ✓ Done!                                                    ║"
echo "║                                                              ║"
echo "║  Run the one-time database setup:                           ║"
echo "║  http://${SERVER_IP}:8080/setup.php                          ║"
echo "║                                                              ║"
echo "║  After setup, delete the setup file:                        ║"
echo "║  docker exec cardrona-app rm /var/www/html/setup.php        ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""
