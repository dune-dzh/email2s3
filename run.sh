#!/usr/bin/env bash
# Single entry point: run this script to set up and run the whole solution. No other commands required.
# Prerequisites: Docker and Docker Compose only. Seed separately so the WebSocket dashboard can show progress.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT_DIR"

# Create .env if it does not exist
if [ ! -f .env ]; then
  echo "==> Creating .env from .env.example"
  cp .env.example .env
fi

# Ensure broadcast uses Reverb (avoids Pusher "No matching application" errors)
if grep -q '^BROADCAST_DRIVER=' .env 2>/dev/null; then
  sed -i.bak 's/^BROADCAST_DRIVER=.*/BROADCAST_DRIVER=reverb/' .env 2>/dev/null || true
else
  echo 'BROADCAST_DRIVER=reverb' >> .env
fi

# RabbitMQ: use non-guest user so migration publisher/workers can connect from Docker (guest is denied from non-localhost)
if grep -q '^RABBITMQ_USER=guest' .env 2>/dev/null; then
  sed -i.bak 's/^RABBITMQ_USER=.*/RABBITMQ_USER=email2s3/' .env 2>/dev/null || true
  sed -i.bak 's/^RABBITMQ_PASSWORD=.*/RABBITMQ_PASSWORD=secret/' .env 2>/dev/null || true
fi

# Configure .env for this server: set APP_URL, REVERB_HOST, MINIO_PUBLIC_ENDPOINT so the app works when accessed (local or external).
# Use PUBLIC_URL to override (required when behind LB with a custom hostname, e.g. PUBLIC_URL=https://app.example.com).
# Otherwise we detect: cloud metadata (AWS/GCP/Azure/DO) then external IP APIs.
detect_public_ip() {
  local ip
  # Cloud metadata (works when host is behind LB; instance sees its own public IP)
  ip=$(curl -s --max-time 2 -H "Metadata-Flavor: Google" "http://metadata.google.internal/computeMetadata/v1/instance/network-interfaces/0/access-configs/0/external-ip" 2>/dev/null) && [ -n "$ip" ] && echo "$ip" && return
  ip=$(curl -s --max-time 2 "http://169.254.169.254/latest/meta-data/public-ipv4" 2>/dev/null) && [ -n "$ip" ] && echo "$ip" && return
  ip=$(curl -s --max-time 2 -H "Metadata: true" "http://169.254.169.254/metadata/instance?api-version=2021-02-01" 2>/dev/null | grep -o '"publicIpAddress":"[^"]*"' | head -1 | sed 's/.*:"\([^"]*\)".*/\1/') && [ -n "$ip" ] && echo "$ip" && return
  ip=$(curl -s --max-time 2 "http://169.254.169.254/metadata/v1/interfaces/public/0/ipv4/address" 2>/dev/null) && [ -n "$ip" ] && echo "$ip" && return
  # External IP APIs (outbound IP as seen by the internet)
  ip=$(curl -s --max-time 3 "https://api.ipify.org" 2>/dev/null) && [ -n "$ip" ] && echo "$ip" && return
  ip=$(curl -s --max-time 3 "https://ifconfig.me/ip" 2>/dev/null) && [ -n "$ip" ] && echo "$ip" && return
  ip=$(curl -s --max-time 3 "https://icanhazip.com" 2>/dev/null) && [ -n "$ip" ] && echo "$ip" && return
  ip=$(curl -s --max-time 3 "http://ifconfig.me" 2>/dev/null) && [ -n "$ip" ] && echo "$ip" && return
  ip=$(curl -s --max-time 3 "http://icanhazip.com" 2>/dev/null) && [ -n "$ip" ] && echo "$ip" && return
  return 1
}

if [ -n "${PUBLIC_URL:-}" ]; then
  SERVER_HOST=$(echo "$PUBLIC_URL" | sed -e 's|^[^/]*//||' -e 's|/.*||' -e 's|:.*||')
  APP_URL_VALUE="${PUBLIC_URL}"
  echo "==> Using PUBLIC_URL for server config: ${PUBLIC_URL}"
else
  PUBLIC_IP=$(detect_public_ip) || true
  if [ -n "${PUBLIC_IP:-}" ]; then
    SERVER_HOST="${PUBLIC_IP}"
    APP_URL_VALUE="http://${PUBLIC_IP}:8080"
    echo "==> Configuring .env for this server (detected public IP: ${PUBLIC_IP})"
  else
    SERVER_HOST="localhost"
    APP_URL_VALUE="http://localhost:8080"
    echo "==> Configuring .env for local use (could not detect public IP; set PUBLIC_URL if behind LB)"
  fi
fi
# Never use empty host (would cause "Host is malformed" in Laravel)
if [ -z "${SERVER_HOST:-}" ]; then
  SERVER_HOST="localhost"
  APP_URL_VALUE="http://localhost:8080"
fi
MINIO_PUBLIC_VALUE="http://${SERVER_HOST}:9000"

set_env_var() {
  local key="$1"
  local value="$2"
  if grep -q "^${key}=" .env 2>/dev/null; then
    sed -i.bak "s#^${key}=.*#${key}=${value}#" .env 2>/dev/null || true
  else
    echo "${key}=${value}" >> .env
  fi
}
set_env_var "APP_URL" "${APP_URL_VALUE}"
set_env_var "REVERB_HOST" "${SERVER_HOST}"
set_env_var "MINIO_PUBLIC_ENDPOINT" "${MINIO_PUBLIC_VALUE}"

# Remove cached config so composer/artisan see current .env (avoids "Host is malformed" when cache had empty APP_URL/REVERB_HOST)
rm -f bootstrap/cache/config.php

# Export variables from .env for docker compose and this script
set -o allexport
source .env
set +o allexport

if ! command -v docker >/dev/null 2>&1; then
  echo "ERROR: Docker is not installed or not in PATH." >&2
  echo "Install Docker Desktop (or Docker Engine), then run: ./run.sh" >&2
  exit 1
fi

if ! docker compose version >/dev/null 2>&1; then
  echo "ERROR: docker compose is required." >&2
  echo "Install Docker Compose or enable Docker Compose V2, then run: ./run.sh" >&2
  exit 1
fi

echo "==> Creating Docker network (if needed)..."
bash scripts/create_network.sh

echo "==> Ensuring Laravel cache and storage dirs exist (for volume mount)..."
mkdir -p bootstrap/cache \
  storage/framework/cache storage/framework/sessions storage/framework/views storage/logs

echo "==> Building and starting containers..."
docker compose up -d --build

echo "==> Waiting for php-fpm to be ready..."
for i in 1 2 3 4 5 6 7 8 9 10; do
  if docker compose exec php-fpm php -v >/dev/null 2>&1; then break; fi
  sleep 2
done

echo "==> Waiting for PostgreSQL to accept connections..."
for i in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15; do
  if docker compose exec postgres pg_isready -U "${DB_USERNAME}" -d "${DB_DATABASE}" >/dev/null 2>&1; then break; fi
  sleep 2
done

echo "==> Making bootstrap/cache and storage writable in container..."
docker compose exec php-fpm bash -lc "
  mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
  touch storage/logs/laravel.log
  chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
  chmod -R 777 bootstrap/cache storage
"

echo "==> Installing Composer dependencies (inside php-fpm)..."
docker compose exec php-fpm bash -lc '
  if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist --no-scripts && composer dump-autoload -o --no-scripts
  else
    composer dump-autoload -o --no-scripts
  fi
'

echo "==> Ensuring application key and Laravel caches..."
SAFE_APP_URL="${APP_URL_VALUE:-http://localhost:8080}"
SAFE_REVERB_HOST="${SERVER_HOST:-localhost}"
docker compose exec -e SAFE_APP_URL="${SAFE_APP_URL}" -e SAFE_REVERB_HOST="${SAFE_REVERB_HOST}" php-fpm bash -c '
  grep -q "^APP_URL=" .env && sed -i.bak "s|^APP_URL=.*|APP_URL=${SAFE_APP_URL}|" .env || echo "APP_URL=${SAFE_APP_URL}" >> .env
  grep -q "^REVERB_HOST=" .env && sed -i.bak "s|^REVERB_HOST=.*|REVERB_HOST=${SAFE_REVERB_HOST}|" .env || echo "REVERB_HOST=${SAFE_REVERB_HOST}" >> .env
  rm -f bootstrap/cache/config.php
  php artisan key:generate --force
  php artisan package:discover --ansi
  (php artisan storage:link 2>/dev/null || true)
  php artisan config:clear
'

echo "==> Ensuring database schema (emails, files, migration_offsets tables)..."
docker compose exec php-fpm bash -lc "php artisan db:ensure-schema"

echo "==> Waiting for RabbitMQ to accept connections..."
for i in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15; do
  if docker compose exec php-fpm php -r "\$f=@fsockopen('rabbitmq',5672);if(\$f){fclose(\$f);echo 'ok';}" 2>/dev/null | grep -q ok; then break; fi
  sleep 2
done

echo "==> Starting Reverb WebSocket server..."
docker compose exec -d php-fpm bash -lc "pkill -f 'artisan reverb' 2>/dev/null || true; php artisan reverb:start --port=6001"

echo "==> Waiting for Reverb to be ready..."
for i in 1 2 3 4 5 6 7 8 9 10; do
  if docker compose exec php-fpm php -r "\$f=@fsockopen('127.0.0.1',6001);if(\$f){fclose(\$f);echo 'ok';}" 2>/dev/null | grep -q ok; then break; fi
  sleep 1
done

echo "==> Starting migration stats broadcaster..."
docker compose exec -d php-fpm bash -lc "php artisan migration:stats-broadcaster"

echo "==> Starting migration workers..."
docker compose exec -d php-fpm bash -lc "php artisan migration:worker"

echo "==> Starting migration publisher (loop mode)..."
docker compose exec -d php-fpm bash -lc "php artisan emails:migrate-to-s3 --loop"

echo ""
echo "Done. The solution is up and running (only command needed: ./run.sh)."
echo ""
echo "  Web UI (email search & dashboard):  ${APP_URL:-http://localhost:8080}"
echo "  Reverb (WebSocket):                  ws://${REVERB_HOST:-localhost}:6001"
echo "  RabbitMQ management:                 http://localhost:15672"
echo "  MinIO console:                       http://${REVERB_HOST:-localhost}:9001"
echo ""
echo "  .env is configured for this server. To override (e.g. behind LB): PUBLIC_URL=https://app.example.com ./run.sh"
echo "  If connection refused: open ports 8080 and 6001 on the server firewall."
echo "  Optional: to seed data, run in another terminal:  docker compose exec php-fpm php artisan emails:seed --records=100000"
echo "  (WebSocket shows progress during seeding and during migration.)"
echo "  To view logs:                        docker compose logs -f php-fpm"
echo ""

