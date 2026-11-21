# Offline synchronization

Open a list online and wait for **Available offline**. The client verifies the service-worker shell and downloadable assets, fetches private photo thumbnails, and stores the list and photos in IndexedDB. The worker caches a generic offline shell. It never caches private HTTP responses. In a supporting browser, My lists can launch the installed app, and direct list links can reopen the saved list from the same browser profile.

Add, check off, edit, mark unavailable, or record a partial purchase while disconnected. Each change is saved in IndexedDB before optimistic UI feedback. Queue records use a client-generated UUID and the item revision on which the change was based. Newly created items have client-generated UUIDs so later offline operations can refer to them.

On reconnect, opening a list, returning to a tab, or receiving a socket invalidation, the browser submits queued operations in order. Conditional polling checks open lists every 12 seconds while visible. The server locks the list in a database transaction, checks the participant's permission, compares the item version, applies the operation, and writes its receipt. If the connection ends before acknowledgement, retrying the same operation is idempotent. Browsers with Web Locks coordinate syncing across tabs. The refreshed snapshot and removal of acknowledged records are committed together in IndexedDB, so an interruption between server acceptance and local refresh remains recoverable.

A stale revision, removed item, changed permission, or rejected value stops the queue and shows a message. Download queued edits if needed. Where the item still exists, choose to apply a conflict over the latest revision or discard the individual change. Queue processing resumes after resolution. Revoked access removes a local snapshot after an online access check and retains unsynced records for export.

Photo uploads, new invitations, account access and list settings require a connection. Browser support, storage quotas and eviction affect how long offline copies remain available. Installation is user-initiated and per-list shortcuts depend on the browser.

When a direct list link stops opening, use **My lists → Recover unsynced changes** to export the preserved queue. An edit saved under one participant identity requires that identity for synchronization.
