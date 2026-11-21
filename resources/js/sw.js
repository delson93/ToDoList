self.addEventListener('install', event => {
    event.waitUntil((async () => {
        const cache = await caches.open(CACHE)
        await cache.addAll(RESOURCES.map(url => new Request(url, { cache: 'reload', credentials: 'omit' })))
        await self.skipWaiting()
    })())
})

self.addEventListener('activate', event => {
    event.waitUntil((async () => {
        const old = (await caches.keys()).filter(key => key.startsWith('utilynk-shell-') && key !== CACHE)
        // Existing tabs may still load a lazy chunk from the previous release.
        await Promise.all(old.slice(0, -1).map(key => caches.delete(key)))
        await self.clients.claim()
    })())
})

self.addEventListener('message', event => {
    if (event.data !== 'offline-ready' || !event.ports[0]) return
    event.waitUntil((async () => {
        const cache = await caches.open(CACHE)
        const resources = await Promise.all(RESOURCES.map(url => cache.match(url)))
        event.ports[0].postMessage(resources.every(Boolean) ? 'ready' : 'pending')
    })())
})

self.addEventListener('fetch', event => {
    const { request } = event
    const url = new URL(request.url)
    if (request.method !== 'GET' || url.origin !== self.location.origin) return
    if (request.mode === 'navigate' && !url.pathname.startsWith('/api/')) {
        event.respondWith(fetch(request).catch(async () => {
            const cache = await caches.open(CACHE)
            return await cache.match('/offline') || Response.error()
        }))
    } else if (RESOURCES.includes(url.pathname) && url.pathname !== '/offline') {
        event.respondWith(caches.open(CACHE).then(async cache => await cache.match(request) || fetch(request)))
    } else if (url.pathname.startsWith('/build/assets/')) {
        event.respondWith(caches.match(request).then(response => response || fetch(request)))
    }
})
