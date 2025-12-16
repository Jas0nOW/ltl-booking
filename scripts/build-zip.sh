#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR=$(cd "$(dirname "$0")"/.. && pwd)
PLUGIN_SLUG="ltl-bookings"
VERSION_FILE="$ROOT_DIR/ltl-booking.php"
DIST_DIR="$ROOT_DIR/dist"
ZIP_NAME="${PLUGIN_SLUG}-$(grep -E "^\s*define\(\s*'LTLB_VERSION'" "$VERSION_FILE" | sed -E "s/.*'LTLB_VERSION'\s*,\s*'([^']+)'.*/\1/").zip"

mkdir -p "$DIST_DIR"
ZIP_PATH="$DIST_DIR/$ZIP_NAME"

# Build a clean temp dir
TMP_DIR=$(mktemp -d)
trap 'rm -rf "$TMP_DIR"' EXIT

rsync -a --exclude '.git/' \
          --exclude '.github/' \
          --exclude 'docs/' \
          --exclude 'scripts/' \
          --exclude 'node_modules/' \
          --exclude '/vendor/' \
          --exclude '.env' \
          --exclude '*.log' \
          --exclude '.DS_Store' \
          --exclude 'Thumbs.db' \
          --exclude 'dist/' \
          "$ROOT_DIR/" "$TMP_DIR/$PLUGIN_SLUG/"

# Create ZIP
(cd "$TMP_DIR" && zip -r9 "$ZIP_PATH" "$PLUGIN_SLUG")

# SHA256 checksum
SHA_FILE="$DIST_DIR/SHA256SUMS.txt"
(cd "$DIST_DIR" && sha256sum "$ZIP_NAME" > "$SHA_FILE")

echo "Built: $ZIP_PATH"
echo "Checksums: $SHA_FILE"