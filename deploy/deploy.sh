#!/usr/bin/env bash
# ==============================================================================
# AquaOptom Wholesale Beverage CRM — Maintenance Deployment Script
# Usage: ./deploy/deploy.sh [--release=v1.0.0] [--skip-build]
# ==============================================================================

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/aquaoptom}"
: "${READINESS_URL:?Set READINESS_URL to the HTTPS readiness endpoint before deployment}"
[[ "$READINESS_URL" == https://* ]] || { echo "READINESS_URL must use HTTPS"; exit 1; }
RELEASE_TAG=""
SKIP_BUILD=false
for argument in "$@"; do
    case "$argument" in
        --release=*) RELEASE_TAG="${argument#--release=}" ;;
        --skip-build) SKIP_BUILD=true ;;
        *) echo "Unknown argument: $argument"; exit 1 ;;
    esac
done
PHP_BIN="php"
COMPOSER_BIN="composer"
NPM_BIN="npm"

echo "=== AquaOptom CRM Deployment Starting ==="
date -u +"%Y-%m-%d %H:%M:%S UTC"

cd "$APP_DIR/backend"

# 1. Enable Maintenance Mode with retry header
echo "[1/8] Entering maintenance mode..."
$PHP_BIN artisan down --retry=60

# 2. Pull latest code or release tag
echo "[2/8] Fetching git updates..."
git fetch --tags origin
if [[ -n "$RELEASE_TAG" ]]; then
    echo "Checking out release tag: $RELEASE_TAG"
    git checkout "$RELEASE_TAG"
else
    git pull --ff-only origin master
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
if [[ "$SKIP_BUILD" == false ]]; then
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
    supervisorctl restart aquaoptom-worker:*
    supervisorctl restart aquaoptom-reverb
    supervisorctl restart aquaoptom-outbox
elif command -v systemctl &> /dev/null; then
    sudo systemctl restart aquaoptom-worker
    sudo systemctl restart aquaoptom-reverb
    sudo systemctl restart aquaoptom-outbox
fi

# 8. Disable Maintenance Mode
echo "[8/8] Leaving maintenance mode..."
$PHP_BIN artisan up

# 9. Verify Readiness Probe
echo "Verifying application readiness probe..."
sleep 2
READY_HTTP_CODE=$(curl --fail --silent --show-error -o /dev/null -w "%{http_code}" "${READINESS_URL:?Set READINESS_URL to your HTTPS /api/health/ready endpoint}" || true)

if [[ "$READY_HTTP_CODE" == "200" ]]; then
    echo "SUCCESS: Readiness probe passed (HTTP 200 OK)."
else
    $PHP_BIN artisan down --retry=60
    echo "FAILED: Readiness probe returned HTTP $READY_HTTP_CODE. Application remains in maintenance mode."
    exit 1
fi

echo "=== Deployment Finished Successfully ==="
