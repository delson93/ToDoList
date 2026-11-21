# Production verification

## Application and security

- [ ] Verify the GitHub build and backend suite against MySQL.
- [ ] Open the HTTPS homepage and check `/up`, the manifest, the worker and fingerprinted CSS and JavaScript.
- [ ] Create a guest list, upload a photo, generate a QR code, open the invite in a separate browser profile, and check off an item.
- [ ] Revoke an invite, remove a participant, and verify private list routes and photos deny subsequent access.
- [ ] Verify no private content reaches reverse-proxy caches, analytics, logs, or sitemap. Request /.env and confirm that the document root protects it.
- [ ] Exercise queue recovery across offline reloads and concurrent updates. Verify cached photos and keyboard navigation on actual Android and iOS devices.
- [ ] Verify Reverb subscription authorization and updates across browsers if enabled. Stop Reverb and confirm periodic updates continue.
- [ ] Verify the scheduler, backups and recovery, any configured queue worker, and email and Google OAuth when configured.
- [ ] Validate host-level security headers, the intended contact address, privacy policy, and terms before announcing the public service.

## SEO

- [x] Render public content and metadata server-side.
- [x] Provide unique titles and descriptions, canonical URLs, social metadata, structured data, useful internal links, and breadcrumbs on subpages.
- [x] Generate an XML sitemap and robots directives from the configured site URL.
- [x] Exclude private list routes from the sitemap and return noindex headers on private responses.
- [ ] Verify the production site in Search Console and submit the sitemap.
- [ ] Check social link previews and production Core Web Vitals after launch.
