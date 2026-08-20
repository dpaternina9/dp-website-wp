#!/usr/bin/env bash
#
# Build a distributable ZIP for the theme or the plugin.
#
#   bin/dp-build.sh --package=theme --version=1.2.3
#   bin/dp-build.sh --package=core  --version=1.2.3 --out=dist
#
# What it does, in order:
#
#   1. refuses a version that is not semver, and refuses to build at all while
#      no update signing key is compiled into the plugin (see --allow-unkeyed);
#   2. copies the package directory into dist/stage/<slug>, so the version is
#      never stamped into the working tree — a build must not dirty the repo;
#   3. stamps the version into the file headers, the VERSION constant, the
#      package composer.json and readme.txt's Stable tag, where each exists;
#   4. runs `composer install --no-dev` *inside the staged package*, because
#      each package carries its own autoloader (docs/adr/0001 §1) and the zip
#      is the only thing that reaches the site;
#   5. builds production assets, if the repository has a build script;
#   6. deletes development files;
#   7. zips it with exactly one top-level directory, named for the slug.
#
# The ZIP is what WordPress unpacks into wp-content. If its internal directory
# name is wrong the update installs beside the live copy instead of replacing
# it, so step 7 is a PHP ZipArchive call in bin/dp-release.php rather than a
# `zip -r` whose result depends on the current working directory.
#
# shellcheck shell=bash

set -euo pipefail

readonly REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

PACKAGE=""
VERSION=""
OUT_DIR="${REPO_ROOT}/dist"
ALLOW_UNKEYED=0
SKIP_ASSETS=0

die() {
	printf 'dp-build: %s\n' "$1" >&2
	exit 1
}

note() {
	printf '  %s\n' "$1"
}

for arg in "$@"; do
	case "$arg" in
		--package=*)     PACKAGE="${arg#*=}" ;;
		--version=*)     VERSION="${arg#*=}" ;;
		--out=*)         OUT_DIR="${arg#*=}" ;;
		--allow-unkeyed) ALLOW_UNKEYED=1 ;;
		--skip-assets)   SKIP_ASSETS=1 ;;
		*) die "unknown argument: ${arg}" ;;
	esac
done

# --- 1. Validate -------------------------------------------------------------

VERSION="${VERSION#v}"

[[ -n "$PACKAGE" ]] || die "missing --package=theme|core"
[[ -n "$VERSION" ]] || die "missing --version=X.Y.Z"

if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+([-+][0-9A-Za-z.-]+)?$ ]]; then
	die "version '${VERSION}' is not semver"
fi

case "$PACKAGE" in
	theme)
		SOURCE_DIR="${REPO_ROOT}/themes/dpaternina"
		SLUG="dpaternina"
		HEADER_FILE="style.css"
		;;
	core)
		SOURCE_DIR="${REPO_ROOT}/plugins/dp-core"
		SLUG="dp-core"
		HEADER_FILE="dp-core.php"
		;;
	*)
		die "unknown package '${PACKAGE}' (expected 'theme' or 'core')"
		;;
esac

[[ -d "$SOURCE_DIR" ]] || die "package directory not found: ${SOURCE_DIR}"

# A build with no trust anchor produces a ZIP no site will ever be told about,
# because the manifest that points at it cannot be verified. Better to stop here
# than to publish a release that silently never installs.
KEY_LINE="$(grep -o "public const COMPILED = '[^']*';" \
	"${REPO_ROOT}/plugins/dp-core/src/Update/PublicKey.php" || true)"

if [[ "$KEY_LINE" == "public const COMPILED = '';" ]]; then
	if [[ "$ALLOW_UNKEYED" -eq 1 ]]; then
		printf 'dp-build: WARNING — no update signing key is compiled in.\n' >&2
		printf 'dp-build: This ZIP is for local inspection only. Do not publish it.\n' >&2
	else
		die "no update signing key compiled in. Run: php bin/dp-release.php keygen --write"
	fi
fi

printf 'Building %s %s\n' "$SLUG" "$VERSION"

# --- 2. Stage ----------------------------------------------------------------

STAGE_ROOT="${OUT_DIR}/stage"
STAGE_DIR="${STAGE_ROOT}/${SLUG}"

rm -rf "$STAGE_DIR"
mkdir -p "$STAGE_DIR"

# -a preserves nothing that matters here but keeps symlink handling sane; the
# trailing /. copies the contents rather than the directory itself.
cp -R "${SOURCE_DIR}/." "$STAGE_DIR"

# Anything the working tree happened to be carrying goes now, before we stamp.
rm -rf "${STAGE_DIR}/vendor" "${STAGE_DIR}/node_modules"

note "staged into ${STAGE_DIR}"

# --- 3. Stamp the version ----------------------------------------------------

stamp() {
	# stamp <file> <extended-regex> — portable in-place edit. `sed -i` differs
	# between GNU and BSD; writing to a temporary file does not.
	local file="$1" expression="$2"
	[[ -f "$file" ]] || return 0
	sed -E "$expression" "$file" > "${file}.stamped"
	mv "${file}.stamped" "$file"
}

case "$PACKAGE" in
	theme)
		stamp "${STAGE_DIR}/style.css" "s/^Version:[[:space:]]*.*$/Version: ${VERSION}/"
		;;
	core)
		stamp "${STAGE_DIR}/dp-core.php" "s/^([[:space:]]*\*[[:space:]]*Version:[[:space:]]*).*$/\1${VERSION}/"
		stamp "${STAGE_DIR}/dp-core.php" "s/^const VERSION = '.*';$/const VERSION = '${VERSION}';/"
		;;
esac

stamp "${STAGE_DIR}/composer.json" "s/(\"version\"[[:space:]]*:[[:space:]]*\")[^\"]*(\")/\1${VERSION}\2/"
stamp "${STAGE_DIR}/readme.txt" "s/^Stable tag:[[:space:]]*.*$/Stable tag: ${VERSION}/"

if ! grep -qE "(^Version: |Version:[[:space:]]+)${VERSION}\$" "${STAGE_DIR}/${HEADER_FILE}"; then
	die "version stamp did not take in ${HEADER_FILE}"
fi

note "stamped ${VERSION} into ${HEADER_FILE}"

# --- 4. Composer, inside the package -----------------------------------------

if [[ -f "${STAGE_DIR}/composer.json" ]]; then
	( cd "$STAGE_DIR" && composer install --no-dev --optimize-autoloader --no-interaction --no-progress --quiet )
	[[ -f "${STAGE_DIR}/vendor/autoload.php" ]] || die "composer produced no autoloader in ${STAGE_DIR}"
	note "composer install --no-dev produced vendor/autoload.php"
else
	note "no composer.json in this package; skipping composer"
fi

# --- 5. Production assets ----------------------------------------------------

if [[ "$SKIP_ASSETS" -eq 0 ]] && grep -q '"build"' "${REPO_ROOT}/package.json"; then
	( cd "$REPO_ROOT" && npm run build --if-present )
	note "ran npm run build"
else
	note "no npm build script (or --skip-assets); nothing to compile"
fi

# --- 6. Prune development files ----------------------------------------------

# Everything here is either a developer's tooling or a build input. None of it
# is needed to run the package, and every file that ships is a file that has to
# be reviewed one day. `*.src.*` is the convention for the second kind: an
# asset master that something else is generated from and that nothing links to
# — themes/dpaternina/assets/img/dp-mark-gradient.src.png is 928 KB of monogram
# nobody downloads.
while IFS= read -r -d '' path; do
	rm -rf "$path"
done < <(find "$STAGE_DIR" \( \
	-name '.git*' -o \
	-name '.DS_Store' -o \
	-name '*.src.*' -o \
	-name 'node_modules' -o \
	-name 'composer.lock' -o \
	-name 'package.json' -o \
	-name 'package-lock.json' -o \
	-name '*.dist' -o \
	-name '*.map' -o \
	-name 'phpunit.xml' -o \
	-name '.editorconfig' -o \
	-name '.eslintrc*' -o \
	-name '.stylelintrc*' -o \
	-name 'webpack.config.js' \
	\) -print0)

note "pruned development files"

# --- 7. Zip ------------------------------------------------------------------

mkdir -p "$OUT_DIR"
ZIP_PATH="${OUT_DIR}/${SLUG}-${VERSION}.zip"

php "${REPO_ROOT}/bin/dp-release.php" zip \
	--source="$STAGE_DIR" \
	--slug="$SLUG" \
	--out="$ZIP_PATH"

if command -v shasum > /dev/null 2>&1; then
	note "sha256 $(shasum -a 256 "$ZIP_PATH" | cut -d' ' -f1)"
fi

printf '%s\n' "$ZIP_PATH"
