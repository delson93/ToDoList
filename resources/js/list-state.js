import { computed, ref, shallowRef, toRaw } from 'vue'
import { api } from './api'
import { database, pending, cachePhotos, removeOfflineList } from './storage'
import { shellReady } from './pwa'

export function listState(listId) {
    const endpoint = `/api/lists/${listId}`
    const state = shallowRef(null)
    const queue = shallowRef([])
    const blocked = shallowRef(null)
    const ready = ref(false)
    const syncing = ref(false)
    const message = ref('')
    const online = ref(navigator.onLine)
    let etag
    let stopped = false
    let photoVersion
    let refreshingPhotos = false

    const items = computed(() => {
        const visible = new Map(state.value?.items.map(item => [item.id, { ...item }]))
        for (const { operation: op, preview } of queue.value) {
            if (op.type === 'delete') {
                visible.delete(op.item_id)
                continue
            }
            if (op.type === 'comment') continue
            const item = visible.get(op.item_id) || (op.type === 'restore' ? preview : null)
            if (!item && op.type !== 'add') continue
            visible.set(op.item_id, { status: 'pending', ...item, ...op.data, id: op.item_id, version: op.base + 1 })
        }
        return [...visible.values()]
    })

    async function initialize() {
        const cached = await (await database()).get('lists', listId)
        if (cached && (!cached.list.expires_at || Date.parse(cached.list.expires_at) > Date.now())) {
            state.value = cached
            if (!navigator.onLine) ready.value = Boolean(cached.offline)
        }
        queue.value = await pending(listId)
        await synchronize()
    }

    async function refresh(acknowledged) {
        const current = await api(endpoint, { etag })
        const db = await database()
        const tx = db.transaction(['lists', 'operations'], 'readwrite')
        const operations = tx.objectStore('operations')
        for (const sequence of acknowledged) await operations.delete(sequence)
        let offline = ready.value
        if (current.body) {
            const lists = tx.objectStore('lists')
            const stored = await lists.get(listId)
            const oldPhotos = stored?.items.map(item => item.photo).filter(Boolean).join('|')
            const newPhotos = current.body.items.map(item => item.photo).filter(Boolean).join('|')
            offline = Boolean(stored?.offline && oldPhotos === newPhotos)
            await lists.put({ ...current.body, offline })
        }
        const waiting = await operations.index('list').getAll(listId)
        await tx.done
        etag = current.etag
        if (current.body) state.value = current.body
        ready.value = offline
        queue.value = waiting
    }

    async function saveForOffline() {
        const snapshot = state.value
        if (!snapshot || refreshingPhotos || photoVersion === snapshot.list.version) return
        refreshingPhotos = true
        try {
            if (!await shellReady()) return
            await cachePhotos(snapshot)
            const db = await database()
            const current = await db.get('lists', listId)
            if (current?.list.version === snapshot.list.version && state.value === snapshot) {
                await db.put('lists', { ...current, offline: true })
                ready.value = true
                photoVersion = snapshot.list.version
            }
        } catch {
            ready.value = false
        } finally {
            refreshingPhotos = false
        }
    }

    async function flush() {
        queue.value = await pending(listId)
        const acknowledged = []
        for (const entry of queue.value) {
            try {
                await api(endpoint + '/operations', { method: 'POST', data: entry.operation })
                acknowledged.push(entry.sequence)
                blocked.value = null
            } catch (error) {
                if (![403, 404, 409, 422].includes(error.status)) throw error
                blocked.value = { ...entry, message: error.message, status: error.status }
                break
            }
        }
        await refresh(acknowledged)
    }

    async function synchronize() {
        online.value = navigator.onLine
        if (syncing.value || !online.value || stopped) return
        syncing.value = true
        try {
            if (navigator.locks) await navigator.locks.request(`utilynk-sync-${listId}`, flush)
            else await flush()
            message.value = ''
            void saveForOffline()
        } catch (error) {
            queue.value = await pending(listId)
            if ([403, 404].includes(error.status)) {
                state.value = null
                ready.value = false
                etag = null
                await removeOfflineList(listId)
                message.value = queue.value.length
                    ? 'Access to this list ended. Your unsynced changes are still on this device.'
                    : 'This list is no longer available.'
            } else {
                message.value = 'Could not sync. Saved changes will retry automatically.'
            }
        } finally {
            syncing.value = false
        }
    }

    async function enqueue(type, itemId, base, data, preview = null) {
        const entry = {
            listId, preview: preview ? JSON.parse(JSON.stringify(preview)) : null,
            operation: { id: crypto.randomUUID(), item_id: itemId, type, base, data, participant: state.value?.actor.id },
        }
        try {
            await (await database()).add('operations', entry)
            queue.value = await pending(listId)
            void synchronize()
            return true
        } catch {
            message.value = 'This device could not save your change. Free up storage and try again.'
            return false
        }
    }

    async function resolveConflict(retry) {
        const entry = blocked.value
        if (!entry) return
        const update = async () => {
            const db = await database()
            if (retry) {
                const item = state.value?.items.find(item => item.id === entry.operation.item_id)
                if (!item || entry.status !== 409 || entry.operation.type === 'add') return
                await db.put('operations', { listId, preview: entry.preview, sequence: entry.sequence, operation: { ...toRaw(entry.operation), id: crypto.randomUUID(), base: item.version } })
            } else {
                await db.delete('operations', entry.sequence)
            }
            queue.value = await pending(listId)
            blocked.value = null
        }
        if (navigator.locks) await navigator.locks.request(`utilynk-sync-${listId}`, update)
        else await update()
        void synchronize()
    }

    function downloadChanges() {
        const blob = new Blob([JSON.stringify(queue.value.map(entry => entry.operation), null, 2)], { type: 'application/json' })
        const url = URL.createObjectURL(blob)
        const anchor = Object.assign(document.createElement('a'), { href: url, download: `utilynk-changes-${listId}.json` })
        anchor.click()
        setTimeout(() => URL.revokeObjectURL(url), 1000)
    }

    return { state, queue, items, blocked, ready, syncing, message, online, endpoint, initialize, synchronize, enqueue, resolveConflict, downloadChanges, stop: () => { stopped = true } }
}
