#!/usr/bin/env bash
# ==============================================================================
# PropSight Capstone — Restructure Script
#
# What this does:
#   1. Renames api/  -> endpoints/   (your internal backend files)
#   2. Moves 3 files from includes/ -> integrations/ (real 3rd-party API clients)
#      - includes/paymongo.php       -> integrations/paymongo.php
#      - includes/oauth_helpers.php  -> integrations/oauth_helpers.php
#      - includes/email_service.php  -> integrations/email_service.php
#   3. Rewrites every require_once / fetch() / comment reference to the new
#      paths across the whole project (PHP, JS, .htaccess, .md, .json).
#
# HOW TO RUN:
#   1. Copy this file into the ROOT of your PropSight-Capstone git repo
#      (same folder that contains "api/", "includes/", "config.php", etc.)
#   2. Make sure your working tree is clean (commit or stash any pending changes)
#   3. Run:  bash restructure_propsight.sh
#   4. Review the changes:  git status  /  git diff --stat
#   5. Commit:  git add -A && git commit -m "Restructure: split api/ into endpoints/ and integrations/"
#
# Safe to re-run: it checks if api/ or includes/paymongo.php etc still exist
# before doing anything, so running it twice won't double-break things.
# ==============================================================================

set -e

if [ ! -d "api" ]; then
  echo "❌ No 'api/' folder found here. Run this script from your PropSight-Capstone repo root."
  exit 1
fi

if [ -d ".git" ]; then
  if [ -n "$(git status --porcelain)" ]; then
    echo "⚠️  You have uncommitted changes. Please commit or stash first, then re-run."
    exit 1
  fi
else
  echo "⚠️  No .git folder detected here — this script will still work, but you won't"
  echo "    get git rename tracking. Recommended: run this inside your real git repo."
fi

echo "Step 1/4: Recording the list of files currently under api/ ..."
find api -type f > /tmp/_api_files_list.txt
API_FILE_COUNT=$(wc -l < /tmp/_api_files_list.txt)
echo "  Found $API_FILE_COUNT files under api/"

echo "Step 2/4: Rewriting path references across the project (before moving files) ..."
python3 << 'PYEOF'
import os

with open('/tmp/_api_files_list.txt') as f:
    api_paths = [l.strip() for l in f if l.strip()]

include_map = {
    'includes/paymongo.php': 'integrations/paymongo.php',
    'includes/oauth_helpers.php': 'integrations/oauth_helpers.php',
    'includes/email_service.php': 'integrations/email_service.php',
}

replacements = {}
for p in api_paths:
    new = p.replace('api/', 'endpoints/', 1)
    replacements[p] = new
replacements.update(include_map)

# extra known comment-only references worth tidying (safe no-ops if absent)
replacements.update({
    "It reuses the current script's own directory (api/auth/) and just swaps":
        "It reuses the current script's own directory (endpoints/auth/) and just swaps",
    "BASE PATH: /api/<file>.php": "BASE PATH: /endpoints/<file>.php",
    "API: /api/occupancy_report.php": "API: /endpoints/occupancy_report.php",
})

exts = ('.php', '.js', '.htaccess', '.md', '.json')
targets = []
for root, dirs, files in os.walk('.'):
    dirs[:] = [d for d in dirs if d not in ('.git', 'vendor', 'node_modules')]
    for fn in files:
        if fn.endswith(exts) or fn == '.htaccess':
            targets.append(os.path.join(root, fn))

changed = 0
for fp in targets:
    try:
        with open(fp, 'r', encoding='utf-8', errors='ignore') as f:
            content = f.read()
    except Exception:
        continue
    orig = content
    for old, new in replacements.items():
        if old in content:
            content = content.replace(old, new)
    if content != orig:
        with open(fp, 'w', encoding='utf-8') as f:
            f.write(content)
        changed += 1

print(f"  Rewrote references in {changed} files")
PYEOF

echo "Step 3/4: Moving folders/files ..."
if [ -d ".git" ]; then
  MV="git mv"
else
  MV="mv"
fi

$MV api endpoints

mkdir -p integrations
[ -f "includes/paymongo.php" ]      && $MV includes/paymongo.php      integrations/paymongo.php
[ -f "includes/oauth_helpers.php" ] && $MV includes/oauth_helpers.php integrations/oauth_helpers.php
[ -f "includes/email_service.php" ] && $MV includes/email_service.php integrations/email_service.php

echo "Step 4/4: Verifying no stale internal references remain ..."
STALE=$(grep -rn "includes/\(paymongo\|oauth_helpers\|email_service\)" --include="*.php" . 2>/dev/null | grep -v vendor || true)
if [ -n "$STALE" ]; then
  echo "⚠️  Found possibly-stale references — please review:"
  echo "$STALE"
else
  echo "  ✅ No stale includes/ references found."
fi

echo ""
echo "Done! New structure:"
echo "  endpoints/   <- your internal backend (was api/)"
echo "  integrations/ <- real 3rd-party API clients (paymongo, oauth, email)"
echo ""
echo "Next: review with 'git status' and 'git diff --stat', then commit."
echo "NOTE: a pre-existing bug (unrelated to this refactor) was left as-is:"
echo "  assets/js/user-js/saved-inline.js references api/user/create_card_payment.php"
echo "  and check_card_payment_status.php, which don't exist. Real files are"
echo "  create_card_checkout.php and check_card_checkout_status.php."
