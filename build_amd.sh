#!/usr/bin/env bash
# Build AMD modules for local_homepage_config.
#
# Replicates what Moodle's Grunt amd task does for this plugin:
#   1. Adds the Moodle module name to the define() call.
#   2. Minifies with terser (must be installed: npm i -g terser).
#   3. Generates source maps.
#
# Usage:
#   cd /path/to/moodle/local/homepage_config && bash build_amd.sh
#
# Requirements: bash, sed, terser (>= 5).

set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "$0")" && pwd)"
SRC_DIR="$PLUGIN_DIR/amd/src"
BUILD_DIR="$PLUGIN_DIR/amd/build"
COMPONENT="local_homepage_config"

if ! command -v terser &>/dev/null; then
    echo "Error: terser not found. Install with: npm install -g terser" >&2
    exit 1
fi

mkdir -p "$BUILD_DIR"

for src in "$SRC_DIR"/*.js; do
    basename="$(basename "$src" .js)"
    module_name="${COMPONENT}/${basename}"
    min_file="$BUILD_DIR/${basename}.min.js"
    map_file="$BUILD_DIR/${basename}.min.js.map"

    echo "Building $module_name ..."

    # Step 1: Inject the module name into define() and minify with terser.
    sed -E "s|define\s*\(\s*\[|define (\"${module_name}\", [|" "$src" \
        | terser \
            --compress passes=2 \
            --mangle \
            --source-map "filename=${basename}.min.js,url=${basename}.min.js.map" \
            --output "$min_file"

    # Step 2: Ensure "define " has a trailing space so Moodle's PHP loader
    # (lib/javascript.php) does not re-inject the module name at serve time.
    # Terser strips it to "define(" — restore it.
    sed -i -E 's|^define\(|define (|' "$min_file"

    echo "  -> $min_file ($(wc -c < "$min_file") bytes)"
done

echo "Done. Built $(ls "$SRC_DIR"/*.js | wc -l) module(s)."
