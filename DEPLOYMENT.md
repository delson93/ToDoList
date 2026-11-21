# Deploy Utilynk Lists on aaPanel

Use PHP 8.4 or 8.5 with the same version for CLI and PHP-FPM. Enable ctype, curl, DOM, fileinfo, filter, GD with WebP, hash, mbstring, OpenSSL, PDO MySQL, session, tokenizer, XML and its reader/writer, and ZIP. Enable PCNTL and POSIX for CLI process management where available. Use Node.js 24 LTS with npm, Composer 2, Git, curl, flock, and MySQL 8.4 LTS or a compatible supported server.

Install PHP and MySQL in aaPanel. Install Composer by following the official installer and signature-verification instructions, then put the executable on the deploy account's PATH. Install Node.js 24 through aaPanel's Node manager or a trusted package source and expose its bin directory in SSH and post-pull environments. Check `php -v`, `php -m`, `composer --version`, `node -v`, and `npm -v`. Use `UTILYNK_DEPLOY_PATH` if a noninteractive shell selects another version. Laravel runs from Composer dependencies.

## Prepare the application

1. Create a database and dedicated application user manually in aaPanel. Use utf8mb4 and restrict the account to the application database. Use a separate migration credential if the runtime account has only data permissions.
2. Point DNS for `list.utilynk.com` to the server, issue a TLS certificate, and redirect HTTP to HTTPS.
3. Create a dedicated SSH deploy user with write access to its release tree. Give PHP-FPM access to read the code and .env and write to the shared storage tree and Laravel's cache directories. Use a shared group or ACLs. Set .env to mode 0640 and avoid world-writable directories.
4. Copy .env.example on initial setup only. Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://list.utilynk.com`, `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`, and the MySQL connection fields. Keep cookies host-only. Set `PUBLIC_CONTACT_EMAIL` to a working address. Generate the application key once with `php artisan key:generate --force` after installing dependencies. Back up the key alongside the environment configuration.
5. Review migration files and verify a restorable database backup before applying production migrations. On first launch, run `php artisan migrate --force` deliberately on the empty database. For subsequent releases, evaluate migration compatibility with the active and rollback versions before applying them. The deployment script checks for pending migrations and exits before activation. Keep any destructive database change outside automated deployment and get approval after verifying recovery.
6. Keep product photos in private Laravel storage. A public storage symlink is unnecessary for item photos. If another feature uses the public disk, `php artisan storage:link` exposes only `storage/app/public`. Never link the private disk.

## Configure the aaPanel website

Set the website's document root to the Laravel `public` directory. For release-based deployment, use `/www/wwwroot/utilynk-lists/current/public`. Keep uploads, .env, Git, source and vendor files outside the web root. Configure PHP-FPM with aaPanel's generated PHP include. Use the Laravel front controller and configure the service worker to revalidate.

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
location = /sw.js {
    add_header Cache-Control "no-cache" always;
    try_files $uri =404;
}
location ^~ /build/assets/ {
    add_header Cache-Control "public, max-age=31536000, immutable";
    try_files $uri =404;
}
location ~ /\.(?!well-known/) {
    deny all;
}
```

Set `client_max_body_size 6m`, PHP `upload_max_filesize=5M`, `post_max_size=6M`, and a PHP memory limit suitable for GD decoding. Validate and reload the generated Nginx configuration. If aaPanel uses Apache, enable rewrite and allow Laravel's public .htaccess with the same document root. Disable reverse-proxy caching for private routes and restrict access to PHP-FPM sockets.

## GitHub Actions and atomic releases

Create this directory structure under the deploy root.

```text
repository.git/             bare clone of delson93/ToDoList
shared/.env                 production environment
shared/storage/             private shared storage
releases/                   immutable source snapshots
bin/activate-release.sh     reviewed copy of scripts/activate-release.sh
current                     symlink to active release
```

Initialize the bare repository with `git clone --bare https://github.com/delson93/ToDoList.git repository.git`. The host needs read access to the remote. Extract the reviewed initial revision into a staging checkout for the first Composer install, APP_KEY setup, and separately verified schema initialization. Configure the aaPanel website to serve `current/public` after the first activation. Install the reviewed `scripts/activate-release.sh` under `bin/`. Refresh that trusted copy when deployment tooling changes.

Set encrypted production environment secrets `DEPLOY_SSH_KEY`, `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_KNOWN_HOSTS`, `DEPLOY_ROOT`, and optionally `DEPLOY_PORT`. Verify the SSH host key out of band before storing it. Limit the SSH key to the dedicated deploy account. Configure environment approval rules as needed. Pushes and pull requests run checks. To authorize activation, run the workflow on main with **Activate the verified main revision on aaPanel** enabled. The deploy job uses the verified commit SHA after tests and the build pass. GitHub concurrency and a server lock serialize deploys.

A release is prepared before the symlink changes. HTTPS health checks after activation restore the previous symlink if they fail. Review `shared/deployment.log` and keep prior known-good releases. Retain older fingerprinted assets for existing clients. If your PHP-FPM configuration retains stale symlink resolution, set the FastCGI script filename to use `$realpath_root` and reload PHP-FPM through aaPanel. Roll back by running the activation script with the previously verified SHA after checking schema compatibility. Rollback never rolls back database migrations.

## aaPanel Git checkout alternative

If the installed Git deployment feature supports a post-pull hook, set it to run `bash /path/to/checkout/deploy.sh >> /path/outside/webroot/deployment.log 2>&1`. Set the site root to that checkout's `public` directory. If no hook is available, run the script explicitly after updating the checkout. For this in-place approach use a maintenance window, preserve the previously working revision and built assets, and restore them if preparation or the health check fails.

## Scheduler, queue and WebSockets

Schedule Laravel as the runtime owner each minute. Recurrence processing runs hourly.

```cron
* * * * * cd /www/wwwroot/utilynk-lists/current && /path/to/php artisan schedule:run
```

A queue worker can run `php artisan queue:work --sleep=3 --tries=3 --timeout=60` under Supervisor, using the active release as its working directory. Set `UTILYNK_QUEUE_WORKER=1` in the deploy environment when a supervisor restarts workers. Core sync processing works without a queue worker.

Periodic collaboration works by default through authorized conditional requests. For immediate updates, set `BROADCAST_CONNECTION=reverb`, generate separate `REVERB_APP_ID`, `REVERB_APP_KEY`, and `REVERB_APP_SECRET`, and set `REVERB_HOST=list.utilynk.com`, `REVERB_PORT=443`, `REVERB_SCHEME=https`, `REVERB_SERVER_HOST=127.0.0.1`, and `REVERB_SERVER_PORT=8080`. Run `php artisan reverb:start` under Supervisor. Proxy `/app/` and `/apps/` on the same TLS host to the loopback endpoint.

```nginx
location ~ ^/apps?/ {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_read_timeout 90s;
}
```

Set `UTILYNK_REVERB_WORKER=1` when the deploy process should tell Supervisor-managed Reverb to restart. Access to subscription authorization is determined by list membership. An installed application can always fall back to periodic updates.

Google login requires a Google OAuth client with the exact redirect URI `https://list.utilynk.com/account/google/callback`. Set its ID, secret, and redirect in .env. Confirm credentials in a test environment before public release.

Password recovery requires a working SMTP service. Configure `MAIL_MAILER=smtp`, the host, port, credentials and sender address. Verify password reset delivery to an address you control. Avoid Laravel's log mailer in production because it writes outgoing messages and reset links to logs. Omit access logs for the password reset form or strip credential-bearing request paths at the proxy.
