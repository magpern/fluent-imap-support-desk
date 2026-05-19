#!/usr/bin/env bash
#
# Release validation for fluent-imap-support-desk.
# Repository checks: dev tree may contain scripts/docs.
# Artifact checks: production ZIP must be runtime-only.
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
VERIFY_ZIP="${SCRIPT_DIR}/lib/verify-release-zip.py"
PLUGIN_SLUG="fluent-imap-support-desk"
MAIN_FILE="${REPO_ROOT}/${PLUGIN_SLUG}.php"
BUILD_DIR="${FISD_BUILD_DIR:-${REPO_ROOT}/builds}"

fail() {
	echo "ERROR: $*" >&2
	exit 1
}

warn() {
	echo "    WARN: $*"
}

echo "==> Fluent IMAP Support Desk: release audit"
echo "    Root: ${REPO_ROOT}"

echo ""
echo "==> Repository checks"

[[ -f "${MAIN_FILE}" ]] || fail "Missing main plugin file: ${MAIN_FILE}"

grep -q "Plugin Name:" "${MAIN_FILE}" || fail "Plugin header missing Plugin Name"
grep -q "Version:" "${MAIN_FILE}" || fail "Plugin header missing Version"

HEADER_VERSION="$(grep -E '^\s*\*\s*Version:\s*' "${MAIN_FILE}" | head -n1 | sed -E 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//')"
VERSION_CONST="$(grep -E "define\s*\(\s*'BIOPENTRA_INBOX_VERSION'" "${MAIN_FILE}" | head -n1 | sed -E "s/.*'([^']+)'.*/\1/")"
[[ -n "${VERSION_CONST}" ]] || fail "BIOPENTRA_INBOX_VERSION not found"
[[ "${VERSION_CONST}" == "${HEADER_VERSION}" ]] || fail "Version mismatch: header=${HEADER_VERSION} const=${VERSION_CONST}"
echo "    Version: header and BIOPENTRA_INBOX_VERSION = ${VERSION_CONST}"

for req in readme.txt LICENSE uninstall.php CHANGELOG.md README.md; do
	[[ -f "${REPO_ROOT}/${req}" ]] || fail "Missing repository file: ${req}"
done

[[ -d "${REPO_ROOT}/includes" ]] || fail "Missing includes/"
[[ -d "${REPO_ROOT}/assets" ]] || fail "Missing assets/"
[[ -d "${REPO_ROOT}/scripts" ]] || fail "Missing scripts/ (dev tree)"
[[ -d "${REPO_ROOT}/docs" ]] || fail "Missing docs/ (dev tree)"
[[ -f "${REPO_ROOT}/.github/workflows/ci.yml" ]] || fail "Missing .github/workflows/ci.yml"
[[ -f "${REPO_ROOT}/.github/workflows/release.yml" ]] || fail "Missing .github/workflows/release.yml"
[[ -f "${REPO_ROOT}/docs/GITHUB_RELEASE_NOTES_${VERSION_CONST}.md" ]] || fail "Missing docs/GITHUB_RELEASE_NOTES_${VERSION_CONST}.md"

echo "    Dev tree: scripts/, docs/, .github/ present (expected in repo)"

echo "==> PHP syntax lint (repository)"
LINT_FAIL=0
while IFS= read -r -d '' php_file; do
	if ! php -l "${php_file}" >/dev/null 2>&1; then
		echo "    FAIL: ${php_file}" >&2
		LINT_FAIL=1
	fi
done < <(find "${REPO_ROOT}/includes" "${REPO_ROOT}" -maxdepth 1 -name '*.php' -print0)
if [[ "${LINT_FAIL}" -ne 0 ]]; then
	fail "PHP syntax lint failed"
fi
echo "    PHP lint: OK"

echo "    Repository checks passed"

echo ""
echo "==> Release artifact checks"

ZIP_PATH="${BUILD_DIR}/${PLUGIN_SLUG}-${VERSION_CONST}.zip"
if [[ ! -f "${ZIP_PATH}" ]]; then
	echo "    Building release zip (not found: ${ZIP_PATH})"
	bash "${SCRIPT_DIR}/build-zip.sh"
fi

[[ -f "${ZIP_PATH}" ]] || fail "Release zip not found: ${ZIP_PATH}"
echo "    Zip: ${ZIP_PATH}"

[[ -f "${VERIFY_ZIP}" ]] || fail "Missing verifier: ${VERIFY_ZIP}"
python3 "${VERIFY_ZIP}" "${ZIP_PATH}" "${PLUGIN_SLUG}"

echo ""
echo "==> Release audit passed (version ${VERSION_CONST})"
