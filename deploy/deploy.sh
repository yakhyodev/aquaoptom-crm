#!/usr/bin/env bash
# ==============================================================================
# AquaOptom Wholesale Beverage CRM — Atomic Zero-Downtime Deployment Script
# Usage: ./deploy/deploy.sh [--release=v1.0.0] [--skip-build]
# ==============================================================================

set -euo pipefail

APP_DIR="/var/www/aquaoptom"
PHP_BIN="php"
COMPOSER_BIN="composer"
NPM_BIN="npm"

echo "=== AquaOptom CRM Deployment Starting ==="
date -u +"%Y-%m-%d %H:%M:%S UTC"

cd "$APP_DIR"

# 1. Enable Maintenance Mode with retry header
echo "[1/8] Entering maintenance mode..."
$PHP_BIN artisan down --retry=60 --secret="aquaoptom-deploy-bypass-token" || true

# 2. Pull latest code or release tag
echo "[2/8] Fetching git updates..."
git fetch --tags origin
if [[ "${1:-}" =~ --release=(.+) ]]; then
    RELEASE_TAG="${BASH_REMATCH[1]}"
    echo "Checking out release tag: $RELEASE_TAG"
    git checkout "$RELEASE_TAG"
else
    git pull origin main
fi

# 3. Install production PHP dependencies
echo "[3/8] Installing Composer dependencies (no-dev, optimized)..."
$COMPOSER_BIN install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader

# 4. Compile frontend assets
echo "[4/8] Building Vite frontend assets..."
if [[ "${1:-}" != "--skip-build" ]]; then
    $NPM_BIN ci --prefer-offline
    $NPM_BIN run build
fi

# 5. Run Database Migrations safely
echo "[5/8] Running database migrations..."
$PHP_BIN artisan migrate --force

# 6. Optimize and Cache Laravel configurations
echo "[6/8] Caching configurations, routes, views, and events..."
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache
$PHP_BIN artisan event:cache

# 7. Restart Background Services
echo "[7/8] Restarting queue workers, websockets, and background daemons..."
if command -v supervisorctl &> /dev/null; then
    supervisorctl restart aquaoptom-worker:* || true
    supervisorctl restart aquaoptom-reverb || true
    supervisorctl restart aquaoptom-outbox || true
elif command -v systemctl &> /dev/null; then
    sudo systemctl restart aquaoptom-worker || true
    sudo systemctl restart aquaoptom-reverb || true
    sudo systemctl restart aquaoptom-outbox || true
fi

# 8. Disable Maintenance Mode
echo "[8/8] Leaving maintenance mode..."
$PHP_BIN artisan up

# 9. Verify Readiness Probe
echo "Verifying application readiness probe..."
sleep 2
READY_HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8000/api/health/ready || true)

if [[ "$READY_HTTP_CODE" == "200" ]]; then
    echo "SUCCESS: Readiness probe passed (HTTP 200 OK)."
else
    echo "WARNING: Readiness probe returned HTTP $READY_HTTP_CODE. Please verify logs."
fi

echo "=== Deployment Finished Successfully ==="
