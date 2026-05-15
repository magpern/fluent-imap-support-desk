#!/usr/bin/env bash
# Build deployable ZIP: builds/fluent-imap-support-desk-{version}.zip
# Archive root folder: fluent-imap-support-desk/
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SLUG="fluent-imap-support-desk"
MAIN="${ROOT}/${SLUG}.php"
OUT_DIR="${ROOT}/builds"
STAGE_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/fisd-zip.XXXXXX")"

cleanup() { rm -rf "$STAGE_ROOT"; }
trap cleanup EXIT

[[ -f "$MAIN" ]] || { echo "error: missing $MAIN" >&2; exit 1; }

extract_version() {
	local line
	line="$(grep -m1 -iE '^[[:space:]]*\*?[[:space:]]*Version:[[:space:]]*' "$MAIN" 2>/dev/null || true)"
	[[ -n "$line" ]] || { echo "0.0.0"; return; }
	echo "$line" | sed -E 's/^[[:space:]]*\*?[[:space:]]*Version:[[:space:]]*//I' | tr -d '\r' | sed 's/[[:space:]]*$//'
}

VERSION="$(extract_version)"
DEST="${OUT_DIR}/${SLUG}-${VERSION}.zip"
STAGE="${STAGE_ROOT}/${SLUG}"

mkdir -p "$OUT_DIR" "$STAGE"

rsync -a \
	--exclude='.git' \
	--exclude='.gitignore' \
	--exclude='.DS_Store' \
	--exclude='README.md' \
	--exclude='CHANGELOG.md' \
	--exclude='docs/' \
	--exclude='builds/' \
	--exclude='scripts/' \
	--exclude='tests/' \
	--exclude='node_modules/' \
	--exclude='.env' \
	--exclude='.env.*' \
	--exclude='*.log' \
	--exclude='*.sql' \
	--exclude='*.sql.gz' \
	--exclude='*.dump' \
	--exclude='.write-test' \
	"${ROOT}/" "${STAGE}/"

write_zip() {
	local parent="$1" name="$2" zip_path="$3"
	if command -v zip >/dev/null 2>&1; then
		( cd "$parent" && zip -qr "$zip_path" "$name" )
		return
	fi
	python3 - "$parent" "$name" "$zip_path" <<'PY'
import sys, zipfile
from pathlib import Path
parent, name, out = Path(sys.argv[1]), sys.argv[2], Path(sys.argv[3])
root = parent / name
with zipfile.ZipFile(out, "w", compression=zipfile.ZIP_DEFLATED) as zf:
    for path in root.rglob("*"):
        if path.is_file():
            zf.write(path, path.relative_to(parent).as_posix())
PY
}

write_zip "$STAGE_ROOT" "$SLUG" "$DEST"
echo "Built ${DEST} (version ${VERSION})"
