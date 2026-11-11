#!/usr/bin/env sh
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
VERSION=$(sed -n 's/^ \* Version: //p' "$ROOT_DIR/shipbridge.php" | head -n 1)
OUTPUT_DIR="$ROOT_DIR/build"
STAGE_DIR=$(mktemp -d)
PLUGIN_DIR="$STAGE_DIR/shipbridge"

trap 'rm -rf "$STAGE_DIR"' EXIT
mkdir -p "$PLUGIN_DIR" "$OUTPUT_DIR"

cp "$ROOT_DIR/shipbridge.php" "$PLUGIN_DIR/"
cp "$ROOT_DIR/readme.txt" "$ROOT_DIR/LICENSE" "$PLUGIN_DIR/"
cp -R "$ROOT_DIR/includes" "$ROOT_DIR/admin" "$PLUGIN_DIR/"

cd "$STAGE_DIR"
zip -qr "$OUTPUT_DIR/shipbridge-$VERSION.zip" shipbridge
echo "$OUTPUT_DIR/shipbridge-$VERSION.zip"

