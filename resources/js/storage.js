import { openDB } from 'idb'

let connection
export function database() {
    return connection ??= openDB('utilynk-lists', 1, {
        upgrade(db) {
            db.createObjectStore('lists', { keyPath: 'list.id' })
            db.createObjectStore('operations', { keyPath: 'sequence', autoIncrement: true }).createIndex('list', 'listId')
            db.createObjectStore('photos', { keyPath: ['listId', 'url'] })
        },
    })
}

export async function pending(listId) {
    return (await database()).getAllFromIndex('operations', 'list', listId)
}

export async function removeOfflineList(listId) {
    const tx = (await database()).transaction(['lists', 'photos'], 'readwrite')
    await tx.objectStore('lists').delete(listId)
    const keys = await tx.objectStore('photos').getAllKeys(IDBKeyRange.bound([listId, ''], [listId, '\uffff']))
    await Promise.all(keys.map(key => tx.objectStore('photos').delete(key)))
    await tx.done
}

export async function cachePhotos(state) {
    const listId = state.list.id
    const db = await database()
    const expected = new Set(state.items.map(item => item.photo).filter(Boolean))
    const keys = await db.getAllKeys('photos', IDBKeyRange.bound([listId, ''], [listId, '\uffff']))
    const available = new Set(keys.map(([, url]) => url))
    await Promise.all(keys.filter(([, url]) => !expected.has(url)).map(key => db.delete('photos', key)))
    const missing = [...expected].filter(url => !available.has(url))
    for (let offset = 0; offset < missing.length; offset += 4) {
        await Promise.all(missing.slice(offset, offset + 4).map(async url => {
            const response = await fetch(url, { cache: 'no-store' })
            if (!response.ok || !response.headers.get('Content-Type')?.startsWith('image/webp')) {
                throw new Error('A photo has not downloaded yet.')
            }
            await db.put('photos', { listId, url, bytes: await response.arrayBuffer() })
        }))
    }
}
