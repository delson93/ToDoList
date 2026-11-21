#!/usr/bin/env bash
set -Eeuo pipefail
if [[ $# != 2 || ! "$2" =~ ^[a-f0-9]{40}$ ]]; then
    echo "Usage: $0 /absolute/deploy/root COMMIT_SHA" >&2
    exit 2
fi
root="$(realpath "$1")"
revision="$2"
repo="$root/repository.git"
shared="$root/shared"
release="$root/releases/$revision"
previous="$(readlink "$root/current" || true)"
test -r "$shared/.env"
test -d "$shared/storage"
mkdir -p "$root/releases"
exec >>"$shared/deployment.log" 2>&1
exec 8>"$shared/release.lock"
flock -n 8
trap 'printf "[%s] Release %s failed at line %s\n" "$(date -u +%FT%TZ)" "$revision" "$LINENO"' ERR

if [[ "$previous" == "$release" ]]; then
    curl --fail --silent --show-error --max-time 15 https://list.utilynk.com/up >/dev/null
    exit 0
fi

if [[ ! -d "$release" ]]; then
    staging="$(mktemp -d "$root/releases/.stage.XXXXXXXX")"
    trap 'if [[ -n "${staging:-}" ]]; then rm -rf -- "$staging"; fi' EXIT
    git --git-dir="$repo" fetch --no-tags origin '+refs/heads/main:refs/remotes/origin/main'
    git --git-dir="$repo" merge-base --is-ancestor "$revision" refs/remotes/origin/main
    git --git-dir="$repo" archive "$revision" | tar -x -C "$staging"
    cp -a "$staging/storage/." "$shared/storage/"
    rm -rf -- "$staging/storage"
    ln -s "$shared/storage" "$staging/storage"
    ln -s "$shared/.env" "$staging/.env"
    mv "$staging" "$release"
    staging=''
fi

cd "$release"
if [[ ! -f .release-ready ]]; then
    bash deploy.sh --prepare
    printf '%s\n' "$revision" > public/release.txt
    touch .release-ready
fi
if [[ -n "$previous" && -d "$previous/public/build/assets" ]]; then
    cp -an "$previous/public/build/assets/." public/build/assets/
fi

activate() {
    if [[ -n "$1" ]]; then
        ln -sfn "$1" "$root/.current-next"
        mv -Tf "$root/.current-next" "$root/current"
    else
        rm -f -- "$root/current"
    fi
}

activate "$release"
if [[ "$(curl --fail --silent --show-error --max-time 15 "https://list.utilynk.com/release.txt?revision=$revision")" != "$revision" ]] ||
   ! curl --fail --silent --show-error --max-time 15 https://list.utilynk.com/up >/dev/null ||
   ! curl --fail --silent --show-error --max-time 15 https://list.utilynk.com/manifest.webmanifest >/dev/null; then
    activate "$previous"
    echo "Health check failed. Restored the previous document root."
    exit 1
fi
if [[ "${UTILYNK_QUEUE_WORKER:-0}" == 1 ]]; then php artisan queue:restart; fi
if [[ "${UTILYNK_REVERB_WORKER:-0}" == 1 ]]; then php artisan reverb:restart; fi
printf '[%s] Activated %s (previous: %s)\n' "$(date -u +%FT%TZ)" "$revision" "$previous"
