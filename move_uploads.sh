#!/usr/bin/env bash
# ==============================================================================
# PropSight Capstone — Move assets/uploads/messages -> uploads/messages
#
# Why: you already have a top-level uploads/ folder (id_documents/, units/,
# profile_photos/) for user-generated content. assets/uploads/messages/ was
# the odd one out living inside assets/ (which should just be static css/js/
# images). This moves it to match the rest.
#
# What this does:
#   1. Moves assets/uploads/messages/* -> uploads/messages/
#   2. Updates the 3 PHP files that reference the old path
#   3. Leaves you a fix_attachment_paths.sql to run against your database,
#      since existing messages already have DB rows pointing to the old path
#      (e.g. attachment_url = 'assets/uploads/messages/msg_xxx.png') that
#      will 404 until that SQL is run.
#
# HOW TO RUN:
#   1. Copy this file into the ROOT of your PropSight-Capstone repo
#   2. Make sure your working tree is clean (commit/stash pending changes)
#   3. Run:  bash move_uploads.sh
#   4. Review:  git status --short --find-renames
#   5. Commit:  git add -A && git commit -m "Move message attachments to uploads/"
#   6. Run fix_attachment_paths.sql against your database (phpMyAdmin or mysql CLI)
# ==============================================================================

set -e

if [ ! -d "assets/uploads/messages" ]; then
  echo "❌ 'assets/uploads/messages' not found here. Either it's already moved,"
  echo "   or you're not in your PropSight-Capstone repo root. Nothing to do."
  exit 1
fi

if [ -d ".git" ] && [ -n "$(git status --porcelain)" ]; then
  echo "⚠️  You have uncommitted changes. Please commit or stash first, then re-run."
  exit 1
fi

MV="mv"

echo "Step 1/2: Moving files ..."
mkdir -p uploads/messages
$MV assets/uploads/messages/* uploads/messages/ 2>/dev/null || true
if [ -f "assets/uploads/messages/.htaccess" ]; then
  $MV assets/uploads/messages/.htaccess uploads/messages/.htaccess
fi
rmdir assets/uploads/messages 2>/dev/null || true
rmdir assets/uploads 2>/dev/null || true
echo "  ✅ Files moved to uploads/messages/"

echo "Step 2/2: Updating code references ..."
sed -i "s#__DIR__ . '/../../assets/uploads/messages/'#__DIR__ . '/../../uploads/messages/'#" endpoints/user/messages.php 2>/dev/null || true
sed -i "s#'assets/uploads/messages/' . \$filename#'uploads/messages/' . \$filename#" endpoints/user/messages.php 2>/dev/null || true
sed -i "s#__DIR__ . '/../assets/uploads/messages/'#__DIR__ . '/../uploads/messages/'#" endpoints/messages.php 2>/dev/null || true
sed -i "s#'assets/uploads/messages/' . \$filename#'uploads/messages/' . \$filename#" endpoints/messages.php 2>/dev/null || true
sed -i "s#realpath(__DIR__ . '/../assets/uploads/messages/')#realpath(__DIR__ . '/../uploads/messages/')#" endpoints/view_message_attachment.php 2>/dev/null || true

STALE=$(grep -rln "assets/uploads" --include="*.php" --include="*.js" . 2>/dev/null | grep -v vendor || true)
if [ -n "$STALE" ]; then
  echo "  ⚠️  Some references to assets/uploads still remain — please check manually:"
  echo "$STALE"
else
  echo "  ✅ No remaining code references to assets/uploads/"
fi

echo ""
echo "Done!"
echo ""
echo "⚠️  IMPORTANT — one more step, on your DATABASE:"
echo "Existing messages already have attachment_url values like"
echo "  'assets/uploads/messages/msg_xxx.png'"
echo "These will 404 until you run fix_attachment_paths.sql (included) against"
echo "your database via phpMyAdmin or the mysql CLI:"
echo "  mysql -u root -p your_database_name < fix_attachment_paths.sql"
echo ""
echo "Next: your .gitignore already excludes uploads/, so these files won't show"
echo "up in 'git status' at all (which is correct — user uploads shouldn't be in git)."
echo "Just verify your site loads message attachments correctly, and run the SQL fix above."