#!/usr/bin/env bash
#
# Build a production release ZIP for fluent-imap-support-desk.
# Does not run git commands. Excludes dev-only paths.
#
set -euo pipefail

readonly PLUGIN_SLUG="fluent-imap-support-desk"
readonly VPS_SOURCE="/home/magpern/fluent-imap-support-desk-repo"
readonly VPS_BUILD_DIR="${VPS_SOURCE}/builds"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
VERIFY_ZIP="${SCRIPT_DIR}/lib/verify-release-zip.py"

if [[ -n "${FISD_SOURCE:-}" ]]; then
	SOURCE="${FISD_SOURCE}"
elif [[ -f "${VPS_SOURCE}/${PLUGIN_SLUG}.php" ]]; then
	SOURCE="${VPS_SOURCE}"
else
	SOURCE="${REPO_ROOT}"
fi

if [[ -n "${FISD_BUILD_DIR:-}" ]]; then
	BUILD_DIR="${FISD_BUILD_DIR}"
elif [[ "${SOURCE}" == "${VPS_SOURCE}" ]]; then
	BUILD_DIR="${VPS_BUILD_DIR}"
else
	BUILD_DIR="${SOURCE}/builds"
fi

readonly SOURCE BUILD_DIR
readonly MAIN_FILE="${SOURCE}/${PLUGIN_SLUG}.php"

echo "==> Fluent IMAP Support Desk: build production release zip"
echo "    Source: ${SOURCE}"

[[ -f "${MAIN_FILE}" ]] || {
	echo "ERROR: Main plugin file not found: ${MAIN_FILE}" >&2
	exit 1
}

HEADER_VERSION="$(
	grep -E '^\s*\*\s*Version:\s*' "${MAIN_FILE}" \
		| head -n 1 \
		| sed -E 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//'
)"

VERSION_CONST="$(
	grep -E "define\s*\(\s*'BIOPENTRA_INBOX_VERSION'" "${MAIN_FILE}" \
		| head -n 1 \
		| sed -E "s/.*'([^']+)'.*/\1/"
)"

if [[ -z "${HEADER_VERSION}" || -z "${VERSION_CONST}" ]]; then
	echo "ERROR: Could not read Version / BIOPENTRA_INBOX_VERSION from ${MAIN_FILE}" >&2
	exit 1
fi

if [[ "${HEADER_VERSION}" != "${VERSION_CONST}" ]]; then
	echo "ERROR: Plugin header Version (${HEADER_VERSION}) does not match BIOPENTRA_INBOX_VERSION (${VERSION_CONST})." >&2
	exit 1
fi

readonly VERSION="${VERSION_CONST}"
readonly ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"
readonly ZIP_PATH="${BUILD_DIR}/${ZIP_NAME}"
readonly STAGING_DIR="${BUILD_DIR}/.package-${PLUGIN_SLUG}"
readonly PACKAGE_DIR="${STAGING_DIR}/${PLUGIN_SLUG}"

echo "    Version: ${VERSION}"
echo "    Output:  ${ZIP_PATH}"

rm -rf "${STAGING_DIR}"
mkdir -p "${PACKAGE_DIR}" "${BUILD_DIR}"

echo "==> Copying production files (excluding dev-only paths)"
tar -C "${SOURCE}" \
	--exclude='.git' \
	--exclude='.github' \
	--exclude='vendor' \
	--exclude='node_modules' \
	--exclude='scripts' \
	--exclude='tests' \
	--exclude='docs' \
	--exclude='build' \
	--exclude='builds' \
	--exclude='.phpcs-cache' \
	--exclude='.phpunit.result.cache' \
	--exclude='README.md' \
	--exclude='CHANGELOG.md' \
	--exclude='.gitignore' \
	--exclude='.editorconfig' \
	--exclude='.cursorignore' \
	--exclude='.env' \
	--exclude='.env.*' \
	--exclude='*.log' \
	--exclude='*.sql' \
	--exclude='*.sql.gz' \
	--exclude='*.dump' \
	--exclude='*.sqlite' \
	--exclude='.write-test' \
	--exclude='.DS_Store' \
	--exclude='Thumbs.db' \
	-cf - . \
	| tar -C "${PACKAGE_DIR}" -xf -

echo "==> Creating zip archive"
rm -f "${ZIP_PATH}"
if command -v zip >/dev/null 2>&1; then
	(
		cd "${STAGING_DIR}"
		zip -rq "${ZIP_PATH}" "${PLUGIN_SLUG}"
	)
else
	echo "    (zip not found; using python3)"
	python3 - "${STAGING_DIR}" "${ZIP_PATH}" "${PLUGIN_SLUG}" <<'PY'
import os
import sys
import zipfile
from pathlib import Path

staging_dir = Path(sys.argv[1])
zip_path = Path(sys.argv[2])
plugin_slug = sys.argv[3]
root = staging_dir / plugin_slug

with zipfile.ZipFile(zip_path, "w", compression=zipfile.ZIP_DEFLATED) as zf:
    for dirpath, _dirnames, filenames in os.walk(root):
        for name in filenames:
            full = Path(dirpath) / name
            zf.write(full, full.relative_to(staging_dir).as_posix())
PY
fi

rm -rf "${STAGING_DIR}"

echo "==> Verifying production zip"
python3 "${VERIFY_ZIP}" "${ZIP_PATH}" "${PLUGIN_SLUG}"

echo "==> Zip summary"
python3 - "${ZIP_PATH}" "${PLUGIN_SLUG}" <<'PY'
import sys
import zipfile
from collections import Counter

zip_path, slug = sys.argv[1], sys.argv[2]
prefix = f"{slug}/"
with zipfile.ZipFile(zip_path) as zf:
    names = sorted(n for n in zf.namelist() if n.startswith(prefix))
    tops = Counter(n[len(prefix) :].split("/")[0] for n in names if n != prefix)
    print(f"    Path: {zip_path}")
    print(f"    Total entries: {len(names)}")
    print("    Top-level under plugin root:")
    for key in sorted(tops):
        print(f"      {key}/  ({tops[key]} paths)")
PY

echo "==> ${ZIP_PATH}"
echo "==> Build complete."
