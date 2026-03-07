#!/bin/bash
#
# Build a clean plugin zip for WordPress installation.
# Usage: ./build-plugin-zip.sh
#
# Outputs: anthologize.zip in the current directory

set -e

PLUGIN_SLUG="anthologize"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
BUILD_DIR=$(mktemp -d)
DEST="$BUILD_DIR/$PLUGIN_SLUG"
OUTPUT="$SCRIPT_DIR/$PLUGIN_SLUG.zip"

echo "Building $PLUGIN_SLUG.zip..."

# Copy all plugin files to temp directory
cp -a "$SCRIPT_DIR" "$DEST"

# Remove items listed in .distignore
while IFS= read -r pattern; do
    # Skip comments and blank lines
    [[ -z "$pattern" || "$pattern" == \#* ]] && continue
    # Use eval to expand globs, remove matching files/dirs
    eval rm -rf "$DEST"/$pattern 2>/dev/null || true
done < "$SCRIPT_DIR/.distignore"

# Remove build tooling from the zip
rm -f "$DEST/build-plugin-zip.sh"
rm -f "$DEST/.distignore"
rm -f "$DEST/$PLUGIN_SLUG.zip"

# Build the zip
cd "$BUILD_DIR"
rm -f "$OUTPUT"
zip -r -q "$OUTPUT" "$PLUGIN_SLUG"

# Clean up
rm -rf "$BUILD_DIR"

# Report final size
SIZE=$(du -h "$OUTPUT" | cut -f1)
echo "Created $OUTPUT ($SIZE)"
