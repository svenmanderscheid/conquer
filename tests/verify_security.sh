#!/usr/bin/env bash
# ============================================================================
# Conquer — Post-Deploy Security Verification
# ============================================================================
#
# Usage: bash tests/verify_security.sh [base_url]
#
# Examples:
#   bash tests/verify_security.sh https://conquer.svenmanderscheid.lu
#   bash tests/verify_security.sh http://conquer.local
#   bash tests/verify_security.sh http://localhost/conquer
#
# Tests that all sensitive paths return 403 Forbidden.
# Run this AFTER every deployment to production.
# ============================================================================

BASE_URL="${1:-https://conquer.svenmanderscheid.lu}"
BASE_URL="${BASE_URL%/}"  # Strip trailing slash if present

# Color output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No color

PASS=0
FAIL=0
TOTAL=0

echo "🔒 Security verification for: $BASE_URL"
echo "================================================================"
echo ""

# Function to check a URL — expects 403 (or 404 acceptable for some)
check_blocked() {
    local path="$1"
    local description="$2"
    TOTAL=$((TOTAL+1))
    
    local status=$(curl -s -o /dev/null -w "%{http_code}" -L --max-redirs 0 "$BASE_URL$path" 2>/dev/null || echo "ERR")
    
    # 403 is what we want; 404 is also acceptable (file doesn't exist publicly)
    if [ "$status" = "403" ]; then
        echo -e "  ${GREEN}✓${NC} [$status] $path  ($description)"
        PASS=$((PASS+1))
    elif [ "$status" = "404" ]; then
        echo -e "  ${GREEN}✓${NC} [$status] $path  (not found — acceptable)"
        PASS=$((PASS+1))
    elif [ "$status" = "200" ]; then
        echo -e "  ${RED}✗ FAIL${NC} [$status] $path  (LEAKED! $description)"
        FAIL=$((FAIL+1))
    else
        echo -e "  ${YELLOW}?${NC} [$status] $path  ($description)"
        FAIL=$((FAIL+1))
    fi
}

# Function to check a URL — expects 200 (the homepage)
check_accessible() {
    local path="$1"
    local description="$2"
    TOTAL=$((TOTAL+1))
    
    local status=$(curl -s -o /dev/null -w "%{http_code}" -L "$BASE_URL$path" 2>/dev/null || echo "ERR")
    
    if [ "$status" = "200" ]; then
        echo -e "  ${GREEN}✓${NC} [$status] $path  ($description)"
        PASS=$((PASS+1))
    else
        echo -e "  ${RED}✗ FAIL${NC} [$status] $path  ($description should be accessible)"
        FAIL=$((FAIL+1))
    fi
}

echo "📍 Should be ACCESSIBLE (200):"
check_accessible "/" "Homepage"
echo ""

echo "🚫 Should be BLOCKED (403/404) — Game data:"
check_blocked "/data/" "Data folder index"
check_blocked "/data/charms.json" "Charms data file"
check_blocked "/data/monsters.json" "Monster balance file"
check_blocked "/data/world_spawn.json" "World spawn config"
echo ""

echo "🚫 Should be BLOCKED (403/404) — Source code:"
check_blocked "/src/" "Source folder index"
check_blocked "/src/README.md" "Source readme"
check_blocked "/src/.htaccess" "Source htaccess"
echo ""

echo "🚫 Should be BLOCKED (403/404) — Configuration:"
check_blocked "/config/" "Config folder"
check_blocked "/config/database.example.php" "Database config template"
check_blocked "/config/app.example.php" "App config template"
check_blocked "/config/database.php" "Production DB config (CRITICAL)"
echo ""

echo "🚫 Should be BLOCKED (403/404) — Migrations and CLI scripts:"
check_blocked "/migrations/" "Migrations folder"
check_blocked "/migrations/run.php" "Migration runner"
check_blocked "/cron/" "Cron folder"
check_blocked "/tests/" "Tests folder"
check_blocked "/tests/verify_security.sh" "This script"
echo ""

echo "🚫 Should be BLOCKED (403/404) — Documentation:"
check_blocked "/docs/" "Docs folder"
check_blocked "/docs/SPEC.md" "Master specification"
check_blocked "/docs/CLAUDE.md" "Claude orientation"
check_blocked "/docs/HOSTINGER_SETUP.md" "Hostinger setup"
echo ""

echo "🚫 Should be BLOCKED (403/404) — Git and dotfiles:"
check_blocked "/.git/" "Git folder"
check_blocked "/.git/config" "Git config (would leak repo URL)"
check_blocked "/.git/HEAD" "Git HEAD"
check_blocked "/.gitignore" "Git ignore"
check_blocked "/.htaccess" "Apache htaccess"
echo ""

echo "🚫 Should be BLOCKED (403/404) — Sensitive root files:"
check_blocked "/README.md" "Project readme"
echo ""

echo "================================================================"
echo "Results: $PASS / $TOTAL passed"
echo ""

if [ $FAIL -eq 0 ]; then
    echo -e "${GREEN}✓ ALL SECURITY CHECKS PASSED${NC}"
    echo "Site is safe to keep running."
    exit 0
else
    echo -e "${RED}✗ $FAIL SECURITY CHECKS FAILED${NC}"
    echo ""
    echo "IMMEDIATE ACTIONS:"
    echo "  1. Verify .htaccess files exist in all blocked folders"
    echo "  2. Verify root .htaccess has the RedirectMatch rules"
    echo "  3. Check Hostinger AllowOverride is enabled"
    echo "  4. If credentials are exposed (e.g. /config/database.php returned 200),"
    echo "     ROTATE THE DATABASE PASSWORD IMMEDIATELY before fixing the leak."
    exit 1
fi
