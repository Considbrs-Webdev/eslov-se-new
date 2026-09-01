#!/usr/bin/env bash
set -euo pipefail

# Atomic release: extract a tarball under $SITE_ROOT/releases and point htdocs at it.
#
# Required:
#   SITE_ROOT   Absolute path to the site folder (config/, releases/, uploads/, htdocs).
#               Set from GitHub Actions as vars.DEPLOY_WEB_ROOT.
#
# Optional:
#   TAR_PATH           Path to release.tar.gz (default: $SITE_ROOT/releases/release.tar.gz)
#   KEEP_RELEASES      How many release dirs/tarballs to keep (default: 5)
#   HTDOCS_NAME        Symlink name under SITE_ROOT (default: htdocs)
#   CONFIG_PATH        (default: $SITE_ROOT/config)
#   UPLOADS_PATH       (default: $SITE_ROOT/uploads)
#   LANGUAGES_PATH     (default: $SITE_ROOT/languages)
#   BLADE_CACHE_PATH   Cleared if the directory exists (default: $SITE_ROOT/tmp/blade-cache)
#   LS_CACHE_PATH      Cleared if set and the directory exists
#
# Usage:
#   SITE_ROOT=/path/to/municipio bash create-release.sh [/path/to/release.tar.gz]

SITE_ROOT="${SITE_ROOT:-}"
if [ -z "$SITE_ROOT" ]; then
  echo "Error: SITE_ROOT is required (GitHub var DEPLOY_WEB_ROOT)." >&2
  exit 1
fi

if [[ "$SITE_ROOT" != /* ]]; then
  echo "Error: SITE_ROOT must be an absolute path (got: $SITE_ROOT)" >&2
  exit 1
fi

if [[ "$SITE_ROOT" == *..* ]]; then
  echo "Error: SITE_ROOT must not contain '..' (got: $SITE_ROOT)" >&2
  exit 1
fi

if [ ! -d "$SITE_ROOT" ]; then
  echo "Error: SITE_ROOT does not exist: $SITE_ROOT" >&2
  exit 1
fi

if command -v realpath >/dev/null 2>&1; then
  SITE_ROOT="$(realpath -e "$SITE_ROOT")"
else
  SITE_ROOT="$(readlink -f "$SITE_ROOT")"
fi

path_is_inside() {
  local parent=$1
  local child=$2
  case "$child" in
    "$parent"|"$parent"/*) return 0 ;;
    *) return 1 ;;
  esac
}

require_inside_site() {
  local label=$1
  local path=$2
  if ! path_is_inside "$SITE_ROOT" "$path"; then
    echo "Error: $label is outside SITE_ROOT: $path" >&2
    exit 1
  fi
}

TAR_PATH="${1:-${TAR_PATH:-$SITE_ROOT/releases/release.tar.gz}}"
KEEP_RELEASES="${KEEP_RELEASES:-5}"
HTDOCS_NAME="${HTDOCS_NAME:-htdocs}"
CONFIG_PATH="${CONFIG_PATH:-$SITE_ROOT/config}"
UPLOADS_PATH="${UPLOADS_PATH:-$SITE_ROOT/uploads}"
LANGUAGES_PATH="${LANGUAGES_PATH:-$SITE_ROOT/languages}"
BLADE_CACHE_PATH="${BLADE_CACHE_PATH:-$SITE_ROOT/tmp/blade-cache}"
LS_CACHE_PATH="${LS_CACHE_PATH:-}"
RELEASES_DIR="$SITE_ROOT/releases"
PRIVATE_PLUGINS_DIR="$SITE_ROOT/plugins"
SYMLINK_PATH="$SITE_ROOT/$HTDOCS_NAME"

if [[ "$HTDOCS_NAME" == */* ]] || [[ "$HTDOCS_NAME" == *..* ]] || [ -z "$HTDOCS_NAME" ]; then
  echo "Error: HTDOCS_NAME must be a single path segment (got: $HTDOCS_NAME)" >&2
  exit 1
fi

if [ ! -f "$TAR_PATH" ]; then
  echo "Error: tar file not found: $TAR_PATH" >&2
  exit 1
fi

if command -v realpath >/dev/null 2>&1; then
  TAR_PATH="$(realpath -e "$TAR_PATH")"
else
  TAR_PATH="$(readlink -f "$TAR_PATH")"
fi
require_inside_site "tarball" "$TAR_PATH"
require_inside_site "releases dir" "$RELEASES_DIR"
require_inside_site "htdocs path" "$SYMLINK_PATH"

if ! [[ "$KEEP_RELEASES" =~ ^[0-9]+$ ]] || [ "$KEEP_RELEASES" -lt 1 ]; then
  echo "Error: KEEP_RELEASES must be a positive integer (got: $KEEP_RELEASES)" >&2
  exit 1
fi

DATE=$(date +%F)

compute_hash() {
  local file=$1
  if [ ! -e "$file" ]; then
    echo ""
    return
  fi

  if stat -c %Y "$file" >/dev/null 2>&1; then
    stat -c %Y "$file"
    return
  fi

  if stat -f %m "$file" >/dev/null 2>&1; then
    stat -f %m "$file"
    return
  fi

  if command -v python3 >/dev/null 2>&1; then
    python3 -c 'import os,sys;print(int(os.path.getmtime(sys.argv[1])))' "$file"
    return
  fi

  echo ""
}

link_if_exists() {
  local source=$1
  local dest=$2
  local label=$3

  if [ -e "$source" ] || [ -L "$source" ]; then
    echo "Linking $label: $dest -> $source"
    ln -sfn "$source" "$dest"
  else
    echo "Skipping $label symlink (source not found: $source)"
  fi
}

FULL_HASH=$(compute_hash "$TAR_PATH")
if [ -z "$FULL_HASH" ]; then
  echo "Warning: unable to compute hash, using timestamp only" >&2
  SHORT_HASH="ts"
else
  SHORT_HASH=${FULL_HASH:0:8}
fi

TARGET_DIR="$RELEASES_DIR/release-${DATE}-${SHORT_HASH}"
PRIVATE_PLUGIN_DEST_ROOT="$TARGET_DIR/wp-content/plugins"

mkdir -p "$RELEASES_DIR"

if [ -e "$TARGET_DIR" ]; then
  echo "Error: target directory already exists: $TARGET_DIR" >&2
  exit 1
fi

mkdir -p "$TARGET_DIR"
require_inside_site "release dir" "$TARGET_DIR"

echo "Extracting $TAR_PATH -> $TARGET_DIR"
if tar -tzf "$TAR_PATH" | grep -E '(^\.\./|/\.\./|^/|\.\.$)' >/dev/null; then
  echo "Error: tarball contains paths that would escape the release directory" >&2
  rm -rf "$TARGET_DIR"
  exit 1
fi
tar -xzf "$TAR_PATH" -C "$TARGET_DIR"

if [ ! -f "$TARGET_DIR/wp-config.php" ] || [ ! -d "$TARGET_DIR/wp" ]; then
  echo "Error: tarball does not look like a Municipio release (missing wp-config.php or wp/)" >&2
  rm -rf "$TARGET_DIR"
  exit 1
fi

if [ -d "$PRIVATE_PLUGINS_DIR" ]; then
  if ! command -v unzip >/dev/null 2>&1; then
    echo "Error: unzip is required to extract plugin ZIPs from $PRIVATE_PLUGINS_DIR" >&2
    exit 1
  fi

  shopt -s nullglob
  plugin_archives=("$PRIVATE_PLUGINS_DIR"/*.zip)
  shopt -u nullglob

  if [ ${#plugin_archives[@]} -eq 0 ]; then
    echo "No private plugin ZIPs found, skipping: $PRIVATE_PLUGINS_DIR"
  else
    mkdir -p "$PRIVATE_PLUGIN_DEST_ROOT"

    for plugin_zip in "${plugin_archives[@]}"; do
      plugin_name=$(basename "$plugin_zip" .zip)
      plugin_dest="$PRIVATE_PLUGIN_DEST_ROOT/$plugin_name"
      extract_tmp_dir=$(mktemp -d "$RELEASES_DIR/.plugin-extract.XXXXXX")
      require_inside_site "plugin extract dir" "$extract_tmp_dir"

      echo "Extracting private plugin ZIP: $(basename "$plugin_zip") -> $plugin_dest"
      unzip -q "$plugin_zip" -d "$extract_tmp_dir"

      rm -rf "$extract_tmp_dir/__MACOSX"
      rm -rf "$plugin_dest"

      if [ -d "$extract_tmp_dir/$plugin_name" ]; then
        mv "$extract_tmp_dir/$plugin_name" "$plugin_dest"
      else
        shopt -s dotglob nullglob
        extracted_entries=("$extract_tmp_dir"/*)
        shopt -u dotglob nullglob

        if [ ${#extracted_entries[@]} -eq 1 ] && [ -d "${extracted_entries[0]}" ]; then
          mv "${extracted_entries[0]}" "$plugin_dest"
        else
          mkdir -p "$plugin_dest"
          shopt -s dotglob nullglob
          extracted_entries=("$extract_tmp_dir"/*)
          shopt -u dotglob nullglob

          if [ ${#extracted_entries[@]} -gt 0 ]; then
            mv "${extracted_entries[@]}" "$plugin_dest"/
          fi
        fi
      fi

      rm -rf "$extract_tmp_dir"
    done
  fi
else
  echo "Private plugins directory not found, skipping: $PRIVATE_PLUGINS_DIR"
fi

link_if_exists "$CONFIG_PATH" "$TARGET_DIR/config" "config"
link_if_exists "$CONFIG_PATH/.htaccess" "$TARGET_DIR/.htaccess" ".htaccess"
link_if_exists "$UPLOADS_PATH" "$TARGET_DIR/wp-content/uploads" "uploads"
link_if_exists "$LANGUAGES_PATH" "$TARGET_DIR/wp-content/languages" "languages"

if [ -d "$TARGET_DIR/wp-content/plugins/advanced-custom-fields-pro" ]; then
  echo "Moving advanced-custom-fields-pro to mu-plugins"
  mkdir -p "$TARGET_DIR/wp-content/mu-plugins"
  rm -rf "$TARGET_DIR/wp-content/mu-plugins/advanced-custom-fields-pro"
  mv "$TARGET_DIR/wp-content/plugins/advanced-custom-fields-pro" \
    "$TARGET_DIR/wp-content/mu-plugins/advanced-custom-fields-pro"
fi

echo "Setting permissions: directories=755, files=644 in $TARGET_DIR"
chmod 755 "$TARGET_DIR"
find "$TARGET_DIR" -type d -exec chmod 755 {} +
find "$TARGET_DIR" -type f -exec chmod 644 {} +

if [ -d "$SYMLINK_PATH" ] && [ ! -L "$SYMLINK_PATH" ]; then
  echo "Error: $SYMLINK_PATH is a real directory. Convert it to a symlink before deploying." >&2
  exit 1
fi

echo "Updating symlink: $SYMLINK_PATH -> $TARGET_DIR"
ln -sfn "$TARGET_DIR" "$SYMLINK_PATH"

if [ -d "$BLADE_CACHE_PATH" ]; then
  if command -v realpath >/dev/null 2>&1; then
    BLADE_CACHE_PATH="$(realpath -e "$BLADE_CACHE_PATH")"
  fi
  if path_is_inside "$SITE_ROOT" "$BLADE_CACHE_PATH"; then
    echo "Clearing blade cache: $BLADE_CACHE_PATH"
    find "$BLADE_CACHE_PATH" -mindepth 1 -xdev -exec rm -rf {} +
  else
    echo "Skipping blade cache clear (path outside SITE_ROOT: $BLADE_CACHE_PATH)"
  fi
fi

if [ -n "$LS_CACHE_PATH" ] && [ -d "$LS_CACHE_PATH" ]; then
  if command -v realpath >/dev/null 2>&1; then
    LS_CACHE_PATH="$(realpath -e "$LS_CACHE_PATH")"
  fi
  if path_is_inside "$SITE_ROOT" "$LS_CACHE_PATH"; then
    echo "Clearing LiteSpeed page cache: $LS_CACHE_PATH"
    find "$LS_CACHE_PATH" -mindepth 1 -xdev -exec rm -rf {} +
  else
    echo "Skipping LiteSpeed cache clear (path outside SITE_ROOT: $LS_CACHE_PATH)"
  fi
fi

WP_PATH="$SYMLINK_PATH/wp"
if command -v wp >/dev/null 2>&1 && [ -d "$WP_PATH" ]; then
  if command -v realpath >/dev/null 2>&1; then
    WP_PATH="$(realpath -e "$WP_PATH")"
  fi
  if path_is_inside "$SITE_ROOT" "$WP_PATH"; then
    echo "Purging LiteSpeed via WP-CLI"
    wp --path="$WP_PATH" litespeed-purge all || echo "Warning: wp litespeed-purge failed (continuing)"
  else
    echo "Skipping WP-CLI purge (path outside SITE_ROOT: $WP_PATH)"
  fi
fi

NEW_TAR_NAME="release-${DATE}-${SHORT_HASH}.tar.gz"
NEW_TAR_PATH="$RELEASES_DIR/$NEW_TAR_NAME"
echo "Renaming tarball: $TAR_PATH -> $NEW_TAR_PATH"
mv "$TAR_PATH" "$NEW_TAR_PATH"

KEEP_TAIL=$((KEEP_RELEASES + 1))
echo "Cleaning up old releases, keeping latest $KEEP_RELEASES..."
shopt -s nullglob

dir_glob=("$RELEASES_DIR"/release-*/)
if [ ${#dir_glob[@]} -gt 0 ]; then
  old_dirs=$(ls -1dt "${dir_glob[@]}" | tail -n "+$KEEP_TAIL") || true
  if [ -n "$old_dirs" ]; then
    while IFS= read -r d; do
      if command -v realpath >/dev/null 2>&1; then
        d="$(realpath -e "$d")"
      fi
      if path_is_inside "$RELEASES_DIR" "$d"; then
        echo "Removing old release dir: $d"
        rm -rf "$d"
      else
        echo "Skipping delete outside releases dir: $d"
      fi
    done <<< "$old_dirs"
  fi
fi

tar_glob=("$RELEASES_DIR"/release-*.tar.gz)
if [ ${#tar_glob[@]} -gt 0 ]; then
  old_tars=$(ls -1t "${tar_glob[@]}" | tail -n "+$KEEP_TAIL") || true
  if [ -n "$old_tars" ]; then
    while IFS= read -r f; do
      if command -v realpath >/dev/null 2>&1; then
        f="$(realpath -e "$f")"
      fi
      if path_is_inside "$RELEASES_DIR" "$f"; then
        echo "Removing old tarball: $f"
        rm -f "$f"
      else
        echo "Skipping delete outside releases dir: $f"
      fi
    done <<< "$old_tars"
  fi
fi
shopt -u nullglob

echo "Created release: $TARGET_DIR"
echo "$HTDOCS_NAME -> $(readlink "$SYMLINK_PATH")"

exit 0
