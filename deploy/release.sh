#!/usr/bin/env bash
# ==============================================================================
# Host-Specific Release & Zero-Downtime Deployment Script
# Section 15.1 (Expand-and-Contract) & Section 15.2 (Topology)
#
# Usage:
#   DEPLOY_PATH=/var/www/dev.kcerp.com RELEASE_ID=$(git rev-parse HEAD) ./deploy/release.sh
#   Or: ./deploy/release.sh [DEPLOY_PATH] [RELEASE_ID]
#
# IMPORTANT:
#   - Zero secrets stored in this repository or script.
#   - Secrets are loaded exclusively from $DEPLOY_PATH/shared/.env on the host.
#   - Traffic switch is atomic via symlink.
#   - Automated rollback to previous release if health checks on /ready fail.
# ==============================================================================

set -euo pipefail

# 1. Environment & Parameter Normalization
DEPLOY_PATH="${DEPLOY_PATH:-${1:-/var/www/dev.kcerp.com}}"
RELEASE_ID="${RELEASE_ID:-${2:-$(date -u +%Y%m%d%H%M%S)}}"
HEALTH_URL="${HEALTH_URL:-http://127.0.0.1:8080/ready}"
MAX_HEALTH_ATTEMPTS="${MAX_HEALTH_ATTEMPTS:-15}"
HEALTH_INTERVAL_SECS="${HEALTH_INTERVAL_SECS:-2}"
KEEP_RELEASES="${KEEP_RELEASES:-5}"
PHP_BIN="${PHP_BIN:-php}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.4-fpm}"

RELEASES_DIR="${DEPLOY_PATH}/releases"
SHARED_DIR="${DEPLOY_PATH}/shared"
CURRENT_LINK="${DEPLOY_PATH}/current"
RELEASE_DIR="${RELEASES_DIR}/${RELEASE_ID}"

echo "===================================================================="
echo " Starting Zero-Downtime Release Deployment"
echo " Target Host Directory : ${DEPLOY_PATH}"
echo " Release Identifier    : ${RELEASE_ID}"
echo " Health Probe URL      : ${HEALTH_URL}"
echo "===================================================================="

# 2. Ensure Required Directory Structure
mkdir -p "${RELEASES_DIR}"
mkdir -p "${SHARED_DIR}/storage/app/public"
mkdir -p "${SHARED_DIR}/storage/framework/cache/data"
mkdir -p "${SHARED_DIR}/storage/framework/sessions"
mkdir -p "${SHARED_DIR}/storage/framework/views"
mkdir -p "${SHARED_DIR}/storage/logs"

# Ensure shared .env exists
if [ ! -f "${SHARED_DIR}/.env" ]; then
    if [ -f "${CURRENT_LINK}/.env" ]; then
        echo "--> Migrating existing .env from current into shared directory..."
        cp "${CURRENT_LINK}/.env" "${SHARED_DIR}/.env"
        chmod 600 "${SHARED_DIR}/.env"
    elif [ -f "${DEPLOY_PATH}/.env" ]; then
        echo "--> Migrating existing .env from deploy root into shared directory..."
        cp "${DEPLOY_PATH}/.env" "${SHARED_DIR}/.env"
        chmod 600 "${SHARED_DIR}/.env"
    else
        echo "ERROR: Missing ${SHARED_DIR}/.env. Application environment configuration must exist on the host." >&2
        exit 1
    fi
fi

# 3. Prepare New Release Directory
if [ ! -d "${RELEASE_DIR}" ]; then
    echo "--> Preparing release directory at ${RELEASE_DIR}..."
    mkdir -p "${RELEASE_DIR}"
    
    # If release code is unpacked in current workspace or temp directory, synchronize it
    SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
    if [ "${SCRIPT_DIR}" != "${RELEASE_DIR}" ]; then
        echo "--> Copying release payload into ${RELEASE_DIR}..."
        rsync -a --delete \
            --exclude '.git' \
            --exclude '.github' \
            --exclude 'node_modules' \
            --exclude 'tests' \
            --exclude '.scratch' \
            "${SCRIPT_DIR}/" "${RELEASE_DIR}/"
    fi
fi

cd "${RELEASE_DIR}"

# 4. Link Shared Assets
echo "--> Linking shared environment and persistent storage..."
ln -sfn "${SHARED_DIR}/.env" "${RELEASE_DIR}/.env"
rm -rf "${RELEASE_DIR}/storage"
ln -sfn "${SHARED_DIR}/storage" "${RELEASE_DIR}/storage"

# 5. Build Dependencies & Optimize Caches
echo "--> Installing composer production dependencies..."
if command -v composer >/dev/null 2>&1; then
    composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --quiet
fi

echo "--> Establishing storage symlink..."
${PHP_BIN} artisan storage:link --quiet || true

echo "--> Building route, view, event and config caches..."
${PHP_BIN} artisan config:cache --quiet
${PHP_BIN} artisan route:cache --quiet
${PHP_BIN} artisan view:cache --quiet
${PHP_BIN} artisan event:cache --quiet

# 6. Database Migrations (Expand Phase - §15.1)
echo "--> Running database migrations as pgsql_owner..."
# Rule: Runs with pgsql_owner connection and strictly additive schema changes
${PHP_BIN} artisan migrate --database=pgsql_owner --force

# 7. Identify Previous Release for Atomic Rollback Guard
PREVIOUS_RELEASE_DIR=""
if [ -L "${CURRENT_LINK}" ]; then
    PREVIOUS_RELEASE_DIR="$(readlink -f "${CURRENT_LINK}")"
    echo "--> Identified previous release at: ${PREVIOUS_RELEASE_DIR}"
elif [ -d "${CURRENT_LINK}" ]; then
    # Legacy migration: current is a directory rather than a symlink
    LEGACY_BACKUP="${RELEASES_DIR}/legacy_$(date +%s)"
    echo "--> Converting legacy directory to symlink. Archiving current to ${LEGACY_BACKUP}..."
    mv "${CURRENT_LINK}" "${LEGACY_BACKUP}"
    PREVIOUS_RELEASE_DIR="${LEGACY_BACKUP}"
fi

# 8. Atomic Symlink Switch
echo "--> Atomically switching traffic to release: ${RELEASE_ID}..."
ln -sfn "${RELEASE_DIR}" "${CURRENT_LINK}.tmp"
mv -Tf "${CURRENT_LINK}.tmp" "${CURRENT_LINK}"

# 9. Reload Runtime Services (PHP-FPM OPcache & Horizon Queue Workers)
echo "--> Reloading PHP-FPM and queue workers..."
if command -v systemctl >/dev/null 2>&1; then
    systemctl reload "${PHP_FPM_SERVICE}" 2>/dev/null || systemctl restart "${PHP_FPM_SERVICE}" 2>/dev/null || true
fi

cd "${CURRENT_LINK}"
${PHP_BIN} artisan horizon:terminate --quiet 2>/dev/null || true
${PHP_BIN} artisan queue:restart --quiet 2>/dev/null || true

# 10. Health Check Probe on /ready
echo "--> Executing post-switch health check against ${HEALTH_URL}..."
HEALTHY=false
ATTEMPT=1

while [ ${ATTEMPT} -le ${MAX_HEALTH_ATTEMPTS} ]; do
    echo "    Health check attempt ${ATTEMPT}/${MAX_HEALTH_ATTEMPTS}..."
    
    # Request HTTP status code and response body
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 5 "${HEALTH_URL}" || echo "000")
    
    if [ "${HTTP_CODE}" = "200" ]; then
        HEALTHY=true
        echo "--> Health check PASSED (HTTP 200). Application is ready."
        break
    fi
    
    echo "    Received HTTP ${HTTP_CODE}. Waiting ${HEALTH_INTERVAL_SECS}s..."
    sleep "${HEALTH_INTERVAL_SECS}"
    ATTEMPT=$((ATTEMPT + 1))
done

# 11. Rollback if Health Checks Fail
if [ "${HEALTHY}" = false ]; then
    echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!" >&2
    echo " FATAL: Health check on ${HEALTH_URL} failed after ${MAX_HEALTH_ATTEMPTS} attempts!" >&2
    echo " Initiating automatic rollback to previous release..." >&2
    echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!" >&2
    
    if [ -n "${PREVIOUS_RELEASE_DIR}" ] && [ -d "${PREVIOUS_RELEASE_DIR}" ]; then
        echo "--> Rolling back symlink to: ${PREVIOUS_RELEASE_DIR}..."
        ln -sfn "${PREVIOUS_RELEASE_DIR}" "${CURRENT_LINK}.tmp"
        mv -Tf "${CURRENT_LINK}.tmp" "${CURRENT_LINK}"
        
        if command -v systemctl >/dev/null 2>&1; then
            systemctl reload "${PHP_FPM_SERVICE}" 2>/dev/null || true
        fi
        
        cd "${CURRENT_LINK}"
        ${PHP_BIN} artisan horizon:terminate --quiet 2>/dev/null || true
        ${PHP_BIN} artisan queue:restart --quiet 2>/dev/null || true
        
        # Tag the failed release directory
        mv "${RELEASE_DIR}" "${RELEASE_DIR}.failed_$(date +%s)"
        echo "--> Rollback complete. Previous release restored." >&2
    else
        echo "CRITICAL: No previous release directory available for rollback!" >&2
    fi
    
    exit 1
fi

# 12. Retention & Housekeeping
echo "--> Pruning old releases (keeping last ${KEEP_RELEASES})..."
cd "${RELEASES_DIR}"
# List directories starting with timestamp or hash, sorted by mtime, delete beyond KEEP_RELEASES
ls -1dt ./* 2>/dev/null | tail -n "+$((KEEP_RELEASES + 1))" | while read -r OLD_RELEASE; do
    if [ -d "${OLD_RELEASE}" ] && [ "$(readlink -f "${CURRENT_LINK}")" != "$(readlink -f "${OLD_RELEASE}")" ]; then
        echo "    Removing obsolete release: ${OLD_RELEASE}"
        rm -rf "${OLD_RELEASE}"
    fi
done

# 13. Set Ownership & Permissions
if command -v id >/dev/null 2>&1 && [ "$(id -u)" = "0" ]; then
    echo "--> Enforcing www-data ownership on shared storage and bootstrap cache..."
    chown -R www-data:www-data "${SHARED_DIR}/storage" "${RELEASE_DIR}/bootstrap/cache" 2>/dev/null || true
    chmod -R 775 "${SHARED_DIR}/storage" "${RELEASE_DIR}/bootstrap/cache" 2>/dev/null || true
fi

echo "===================================================================="
echo " Deployment of release ${RELEASE_ID} completed successfully."
echo " Active Release: $(readlink -f "${CURRENT_LINK}")"
echo "===================================================================="
exit 0
