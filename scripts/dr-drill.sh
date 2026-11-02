#!/usr/bin/env bash
# ==============================================================================
# Disaster Recovery Drill Automation Script (OPS-02, OPS-06)
#
# Restores last night's backup into an isolated scratch database, executes the
# Business Integrity Watchdog, and validates Trial Balances & Stock Valuations
# against the production baseline.
#
# Usage:
#   ./scripts/dr-drill.sh [--backup=/path/to/backup.dump] [--scratch-db=erp_dr_scratch] [--keep] [--json]
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${SCRIPT_DIR}"

BACKUP_ARG=""
SCRATCH_DB_ARG="--scratch-db=erp_dr_scratch"
KEEP_ARG=""
JSON_ARG=""

for arg in "$@"; do
    case "${arg}" in
        --backup=*)
            BACKUP_ARG="${arg}"
            ;;
        --scratch-db=*)
            SCRATCH_DB_ARG="${arg}"
            ;;
        --keep)
            KEEP_ARG="--keep"
            ;;
        --json)
            JSON_ARG="--json"
            ;;
        -h|--help)
            echo "Usage: ./scripts/dr-drill.sh [options]"
            echo "Options:"
            echo "  --backup=/path/to/backup.dump  Specify backup file to restore (defaults to snapshot of current)"
            echo "  --scratch-db=NAME              Scratch database name (default: erp_dr_scratch)"
            echo "  --keep                         Retain scratch database after verification"
            echo "  --json                         Output machine-readable JSON metrics"
            exit 0
            ;;
        *)
            echo "Unknown option: ${arg}" >&2
            exit 1
            ;;
    esac
done

# 1. Verify Prerequisites
command -v php >/dev/null 2>&1 || { echo "ERROR: php binary is required." >&2; exit 1; }
command -v psql >/dev/null 2>&1 || { echo "ERROR: psql binary is required." >&2; exit 1; }
command -v pg_restore >/dev/null 2>&1 || { echo "ERROR: pg_restore binary is required." >&2; exit 1; }

START_TIME=$(date +%s)
echo "===================================================================="
echo " Starting Disaster Recovery Drill [$(date -u '+%Y-%m-%d %H:%M:%S UTC')]"
echo " Working Directory: ${SCRIPT_DIR}"
echo "===================================================================="

# 2. Execute Disaster Recovery Drill Command
# Build argument array
ARGS=()
if [ -n "${BACKUP_ARG}" ]; then ARGS+=("${BACKUP_ARG}"); fi
if [ -n "${SCRATCH_DB_ARG}" ]; then ARGS+=("${SCRATCH_DB_ARG}"); fi
if [ -n "${KEEP_ARG}" ]; then ARGS+=("${KEEP_ARG}"); fi
if [ -n "${JSON_ARG}" ]; then ARGS+=("${JSON_ARG}"); fi

php artisan dr:drill "${ARGS[@]}"

EXIT_CODE=$?
END_TIME=$(date +%s)
TOTAL_ELAPSED=$((END_TIME - START_TIME))

echo "===================================================================="
echo " DR Drill Finished in ${TOTAL_ELAPSED}s with Exit Code ${EXIT_CODE}"
echo "===================================================================="

exit ${EXIT_CODE}
