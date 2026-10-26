#!/usr/bin/env bash
#
# Diwan ERP - updates the Mac install to the latest release.
#
#   bash ~/Diwan/current/deploy/mac/update.sh
#
# Runs daily (com.diwan.update). Prepares the new release next to the running
# one, backs up the database when it changes, then switches over; if the system
# does not answer afterwards it switches back by itself.

set -Eeuo pipefail

REPO=turkialawlqy/Diwan-ERP
PACKAGE_URL=${DIWAN_PACKAGE:-https://github.com/$REPO/releases/latest/download/diwan-erp.zip}
DIR="$HOME/Diwan"
KEEP=3

c_ok=$'\e[32m'; c_err=$'\e[31m'; c_off=$'\e[0m'
ok()  { echo "${c_ok}✓ $*${c_off}"; }
STATUS="$DIR/shared/storage/app/update-status.json"
# What the dashboard shows the owner (state: running | done | failed | uptodate).
status() {
    { printf '{"state":"%s","from":"%s","to":"%s","message":"%s","at":%s}\n' "$1" "${INSTALLED:-}" "${VERSION:-}" "${2:-}" "$(date +%s)" > "$STATUS.tmp" \
        && mv -f "$STATUS.tmp" "$STATUS"; } 2> /dev/null || true
}
die() { echo "${c_err}✗ $*${c_off}" >&2; status failed "$*"; exit 1; }

for b in /opt/homebrew/bin/brew /usr/local/bin/brew; do [[ -x $b ]] && eval "$("$b" shellenv)" && break; done
PHP="$(brew --prefix php@8.3)/bin/php"
if [[ ! -f $HOME/Library/LaunchAgents/com.diwan.update-now.plist ]]; then
    mkdir -p "$DIR/shared/storage/app/update-queue" "$HOME/Library/LaunchAgents"
    cat > "$HOME/Library/LaunchAgents/com.diwan.update-now.plist" <<PLIST
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0"><dict>
    <key>Label</key><string>com.diwan.update-now</string>
    <key>ProgramArguments</key><array><string>/bin/bash</string><string>$DIR/current/deploy/mac/update.sh</string></array>
    <key>QueueDirectories</key><array><string>$DIR/shared/storage/app/update-queue</string></array>
    <key>ThrottleInterval</key><integer>30</integer>
</dict></plist>
PLIST
    launchctl bootstrap "gui/$(id -u)" "$HOME/Library/LaunchAgents/com.diwan.update-now.plist" > /dev/null 2>&1 || true
fi
LOCK="$DIR/.update.lock"
mkdir "$LOCK" 2> /dev/null || die "Another update is already running (if not, delete the folder $LOCK)."
TMP=$(mktemp -d)
trap 'rm -rf "$TMP" "$LOCK"' EXIT

# The dashboard asks for an update by dropping a file in this folder (launchd QueueDirectories).
rm -f "$DIR"/shared/storage/app/update-queue/* 2> /dev/null || true
CURRENT=$(cd "$DIR/current" && pwd -P)
INSTALLED=$(cat "$CURRENT/VERSION" 2> /dev/null || echo 0)

if [[ -f $PACKAGE_URL ]]; then
    cp "$PACKAGE_URL" "$TMP/diwan-erp.zip"
else
    curl -fsSL -o "$TMP/diwan-erp.zip" "$PACKAGE_URL" || die "Could not download the update (internet connection?)."
    if curl -fsSL -o "$TMP/sha" "$PACKAGE_URL.sha256" 2> /dev/null; then
        [[ $(shasum -a 256 "$TMP/diwan-erp.zip" | cut -d' ' -f1) == "$(tr -d '[:space:]' < "$TMP/sha")" ]] || die "The downloaded update is corrupted."
    fi
fi
status running "Downloading"
unzip -q "$TMP/diwan-erp.zip" -d "$TMP"
VERSION=$(cat "$TMP/diwan-erp/VERSION" 2> /dev/null || echo 0)
if [[ $VERSION == "$INSTALLED" && ${1:-} != --force ]]; then
    ok "Diwan is up to date (version $INSTALLED)."
    status uptodate "Up to date"
    exit 0
fi

status running "Installing"
NEW="$DIR/releases/$(date +%Y%m%d%H%M%S)-$VERSION"
mv "$TMP/diwan-erp" "$NEW"
rm -rf "$NEW/storage"
ln -sfn "$DIR/shared/storage" "$NEW/storage"
ln -sfn "$DIR/shared/.env" "$NEW/.env"
ln -sfn ../storage/app/public "$NEW/public/storage"

# Anything failing before the switch leaves the running version untouched.
trap 'rm -rf "$TMP" "$LOCK" "$NEW"; status failed "Update stopped; the previous version keeps running"; echo "${c_err}✗ Update stopped; Diwan keeps running the previous version, unchanged.${c_off}" >&2' ERR
"$PHP" "$NEW/artisan" optimize > /dev/null
if "$PHP" "$NEW/artisan" migrate:status --pending 2> /dev/null | grep -q Pending; then
    "$PHP" "$NEW/artisan" erp:backup --tag=preupdate > /dev/null || die "The backup failed, so the update was cancelled."
    "$PHP" "$NEW/artisan" migrate --force > /dev/null
fi
trap 'rm -rf "$TMP" "$LOCK"' ERR

restart() {
    for label in com.diwan.web com.diwan.queue com.diwan.scheduler; do
        launchctl kickstart -k "gui/$(id -u)/$label" > /dev/null 2>&1 || true
    done
}
ln -sfn "$NEW" "$DIR/current"
restart

PORT=$(grep -E '^APP_URL=' "$DIR/shared/.env" | sed -E 's#.*:([0-9]+)$#\1#')
healthy=0
for _ in 1 2 3 4 5 6 7 8 9 10; do
    sleep 2
    curl -fs -o /dev/null --max-time 10 "http://127.0.0.1:${PORT:-8000}/up" && { healthy=1; break; }
done
if [[ $healthy != 1 ]]; then
    ln -sfn "$CURRENT" "$DIR/current"
    restart
    rm -rf "$NEW"
    die "Version $VERSION did not start; switched back to $INSTALLED automatically."
fi

ls -1d "$DIR/releases"/*/ | sed 's#/$##' | sort -r | tail -n +$((KEEP + 1)) | while read -r old; do
    [[ $old == "$(cd "$DIR/current" && pwd -P)" ]] || rm -rf "$old"
done
status done "Updated"
ok "Updated from $INSTALLED to $VERSION."
