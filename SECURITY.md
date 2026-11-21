# Security and data handling

The private API uses Laravel web sessions, encrypted HttpOnly guest cookies, and Laravel's request-forgery middleware. Configure TLS and secure cookies in production. Each private read, operation, photo request, and subscription resolves membership server-side. UUIDs are locators. Authorization is checked independently of knowledge of a UUID.

Invitation tokens contain 256 bits of randomness. The database stores SHA-256 digests. The link places the token in a URL fragment and the join screen submits it in a same-origin POST. Keep request-body logging and production debug mode disabled. Copy, QR and native sharing use the browser. Choosing WhatsApp sends the invitation URL to WhatsApp. A revoked link cannot authorize future joins. Remove existing participants separately. Use no-referrer and no-store headers on private responses and keep analytics on marketing routes only.

Photos are type and dimension checked, decoded, re-encoded as bounded WebP thumbnails and stored on a private disk. The authorized controller serves them. Use attachment and database backups together. Database backups include list contents and credential digests and should be restricted and encrypted at rest.

An offline list and photo copies reside in the browser profile. Use a device account you control. Server-side revocation takes effect on the next successful authorization check. A device without connectivity may retain previously saved material. Browser storage eviction and clearing site data can remove copies and pending edits. Sign out after syncing changes to clear local copies in browsers supporting Clear-Site-Data.

Broadcasts carry an empty invalidation payload. Each read and change rechecks access. Each active participant receives invalidations on a separately authorized channel. Participant removal excludes that channel from subsequent broadcasts. Reverb and the application keep separate credentials. See [DEPLOYMENT.md](DEPLOYMENT.md) for key handling, HTTPS, uploads and proxy settings.
