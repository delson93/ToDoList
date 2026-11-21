#!/usr/bin/env bash
set -Eeuo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")"

mode="${1:---in-place}"
case "$mode" in --prepare|--in-place) ;; *) echo "Usage: $0 [--prepare|--in-place]" >&2; exit 2 ;; esac
export PATH="${UTILYNK_DEPLOY_PATH:-$PATH}"
exec 9>"${UTILYNK_DEPLOY_LOCK:-/tmp/utilynk-lists-deploy.lock}"
flock -n 9 || { echo 'Another deployment is running.' >&2; exit 1; }
trap 'printf "[%s] Deployment preparation failed at line %s\n" "$(date -u +%FT%TZ)" "$LINENO" >&2' ERR

printf '[%s] Preparing Utilynk Lists release\n' "$(date -u +%FT%TZ)"
for executable in php composer node npm curl; do command -v "$executable" >/dev/null; done
test -r .env
for directory in storage bootstrap/cache; do test -w "$directory"; done
php -r '
    if (PHP_VERSION_ID < 80400) { fwrite(STDERR, "PHP 8.4+ is required by the lock file.\n"); exit(1); }
    foreach (["ctype","curl","dom","fileinfo","filter","gd","hash","mbstring","openssl","pdo_mysql","session","tokenizer","xml"] as $ext) {
        if (!extension_loaded($ext)) { fwrite(STDERR, "Missing PHP extension: $ext\n"); exit(1); }
    }
    if (!function_exists("imagewebp")) { fwrite(STDERR, "GD with WebP support is required.\n"); exit(1); }
'
node --input-type=module -e 'const [major,minor]=process.versions.node.split(".").map(Number); if (major < 22 || (major === 22 && minor < 12)) process.exit(1)'
npm --version
composer --version
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
php artisan optimize:clear
php artisan lists:deployment-check
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache

if [[ "$mode" == '--in-place' ]]; then
    curl --fail --silent --show-error --max-time 15 https://list.utilynk.com/up > /dev/null
    if [[ "${UTILYNK_QUEUE_WORKER:-0}" == 1 ]]; then php artisan queue:restart; fi
    if [[ "${UTILYNK_REVERB_WORKER:-0}" == 1 ]]; then php artisan reverb:restart; fi
fi

printf '[%s] Release prepared successfully\n' "$(date -u +%FT%TZ)"
