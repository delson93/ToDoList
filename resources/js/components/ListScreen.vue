<script setup>
import { computed, defineAsyncComponent, nextTick, onMounted, onUnmounted, ref } from 'vue'
import { api } from '../api'
import { removeOfflineList } from '../storage'
import { listState } from '../list-state'
import ItemRow from './ItemRow.vue'

const ItemEditor = defineAsyncComponent(() => import('./ItemEditor.vue'))
const SharingPanel = defineAsyncComponent(() => import('./SharingPanel.vue'))
const ListSettings = defineAsyncComponent(() => import('./ListSettings.vue'))
const props = defineProps({ listId: { type: String, required: true }, reverb: String, labels: String })
const store = listState(props.listId)
const { state, items, queue, blocked, ready, syncing, message, online } = store
const labels = props.labels ? JSON.parse(props.labels) : {}
const level = computed(() => ['view', 'complete', 'edit', 'collaborate', 'owner'].indexOf(state.value?.actor.role))
const name = ref('')
const nameInput = ref(null)
const shopping = ref(false)
const showShare = ref(false)
const showSettings = ref(false)
const selected = ref(null)
const undo = ref(null)
const note = ref('')
const summary = ref(false)
const starting = ref(true)
const adding = ref(false)
const voice = window.SpeechRecognition || window.webkitSpeechRecognition
const pendingItems = computed(() => items.value.filter(item => item.status !== 'completed'))
const completed = computed(() => items.value.filter(item => item.status === 'completed'))
const counts = computed(() => Object.fromEntries(['pending', 'completed', 'unavailable', 'partial'].map(status => [status, items.value.filter(item => item.status === status).length])))
const totals = computed(() => ['estimated_price', 'actual_price'].map(field => items.value.reduce((sum, item) => sum + Math.round(Number(item[field] || 0) * 100), 0) / 100))
const cachedSuggestions = computed(() => [...new Set([...items.value.map(item => item.name), ...(state.value?.suggestions || [])])])
const canReapply = computed(() => blocked.value?.status === 409 && blocked.value.operation.type !== 'add' && state.value?.items.some(item => item.id === blocked.value.operation.item_id))
const syncLabel = computed(() => {
    if (!online.value) return queue.value.length ? `Offline · ${queue.value.length} unsynced` : 'Offline'
    if (syncing.value && queue.value.length) return 'Syncing…'
    if (queue.value.length) return `${queue.value.length} unsynced`
    return ready.value ? 'Available offline' : 'Preparing offline access…'
})

async function add() {
    const value = name.value.trim()
    if (!value || adding.value) return
    adding.value = true
    try {
        if (await store.enqueue('add', crypto.randomUUID(), 0, { name: value })) {
            name.value = ''
            note.value = `Added ${value}.`
            await nextTick()
            if (document.activeElement?.closest('.add-form')) nameInput.value?.focus()
        }
    } finally {
        adding.value = false
    }
}

async function toggle(item) {
    const status = item.status === 'completed' ? 'pending' : 'completed'
    if (await store.enqueue('status', item.id, item.version, { status })) {
        note.value = status === 'completed' ? `Completed ${item.name}.` : `Restored ${item.name} to pending.`
        await nextTick()
        document.getElementById('check-' + item.id)?.focus({ preventScroll: true })
    }
}

async function deleteItem(item) {
    if (await store.enqueue('delete', item.id, item.version, {})) {
        undo.value = { ...item, comments: item.comments?.map(comment => ({ ...comment })) }
        selected.value = null
        note.value = `Deleted ${item.name}.`
    }
}

async function restoreItem() {
    const item = undo.value
    if (await store.enqueue('restore', item.id, item.version + 1, {}, item)) {
        note.value = `Restored ${item.name}.`
        undo.value = null
    }
}

const listening = ref(false)
const voiceMessage = ref('')
let recognition
let voiceTimeout

function releaseRecognition() {
    clearTimeout(voiceTimeout)
    recognition = null
    listening.value = false
}

function cancelRecognition() {
    const active = recognition
    releaseRecognition()
    active?.abort()
}

function dictate() {
    if (recognition) {
        cancelRecognition()
        voiceMessage.value = ''
        return
    }

    voiceMessage.value = ''
    try {
        const session = new voice()
        recognition = session
        session.lang = document.documentElement.lang || navigator.language
        session.continuous = false
        session.interimResults = false
        let heardSpeech = false

        session.onresult = event => {
            if (recognition !== session) return
            const result = event.results[event.resultIndex ?? 0]
            if (!result?.isFinal) return
            const transcript = result[0]?.transcript?.trim()
            if (transcript) {
                name.value = transcript.slice(0, 150)
                heardSpeech = true
            }
        }
        session.onerror = event => {
            if (recognition !== session) return
            clearTimeout(voiceTimeout)
            voiceTimeout = setTimeout(cancelRecognition, 1000)
            voiceMessage.value = event.error === 'not-allowed' || event.error === 'service-not-allowed'
                ? 'Allow speech recognition in your browser, or use the microphone on your keyboard.'
                : 'Voice input didn’t catch that. Try again or use the microphone on your keyboard.'
        }
        session.onend = () => {
            if (recognition !== session) return
            releaseRecognition()
            if (!heardSpeech && !voiceMessage.value) voiceMessage.value = 'No speech was captured. Try again or type the item name.'
        }
        listening.value = true
        voiceTimeout = setTimeout(() => {
            cancelRecognition()
            voiceMessage.value = 'Voice input timed out. Try again or type the item name.'
        }, 15000)
        session.start()
    } catch {
        releaseRecognition()
        voiceMessage.value = 'Voice input couldn’t start. Try again or use the microphone on your keyboard.'
    }
}

let poll
let echo
let alive = true
const onConnection = () => { online.value = navigator.onLine; void store.synchronize() }
const onVisibility = () => { if (!document.hidden) onConnection() }

onMounted(async () => {
    window.addEventListener('online', onConnection)
    window.addEventListener('offline', onConnection)
    window.addEventListener('focus', onVisibility)
    window.addEventListener('pageshow', onVisibility)
    document.addEventListener('visibilitychange', onVisibility)
    poll = setInterval(onVisibility, 12000)
    try {
        await store.initialize()
    } catch {
        message.value = 'This device could not open its saved storage.'
    } finally {
        starting.value = false
    }
    if (props.reverb && state.value && alive) {
        try {
            const { connect } = await import('../realtime')
            if (alive) echo = connect(props.reverb, props.listId, state.value.actor.id, onConnection)
        } catch {
            // Conditional polling keeps collaboration working without a socket.
        }
    }
})

onUnmounted(() => {
    alive = false
    clearInterval(poll)
    cancelRecognition()
    store.stop()
    echo?.disconnect()
    window.removeEventListener('online', onConnection)
    window.removeEventListener('offline', onConnection)
    window.removeEventListener('focus', onVisibility)
    window.removeEventListener('pageshow', onVisibility)
    document.removeEventListener('visibilitychange', onVisibility)
})

const deletingList = ref(false)

async function deleteList() {
    if (!online.value || deletingList.value) return
    if (!window.confirm(`Delete “${state.value.list.name}” for everyone? This cannot be undone in the app.`)) return
    deletingList.value = true
    try {
        await api(store.endpoint, { method: 'DELETE' })
    } catch (error) {
        message.value = error.message || 'Could not delete the list. Try again.'
        deletingList.value = false
        return
    }
    store.stop()
    try {
        await removeOfflineList(props.listId)
    } finally {
        location.assign('/lists')
    }
}

async function change(suffix, options) {
    const response = await api(store.endpoint + suffix, options)
    void store.synchronize()
    return response.body
}
</script>

<template>
    <div v-if="state" :class="{ shopping }">
        <div class="section-head list-heading">
            <div>
                <a v-if="!shopping" class="text-link tiny" href="/lists">← My lists</a>
                <h1>{{ state.list.name }}</h1>
                <div class="sync-indicator" role="status"><span aria-hidden="true" :class="{ offline: !online, waiting: queue.length }"></span>{{ syncLabel }}</div>
                <p v-if="!online && ready" class="muted tiny">Available offline</p>
            </div>
            <div class="actions list-actions">
                <button v-if="level >= 3 && !shopping" class="button secondary" :aria-expanded="showShare" @click="showShare = !showShare">Share <span aria-hidden="true">↗</span></button>
                <details v-if="!shopping" class="overflow">
                    <summary class="button secondary">More</summary>
                    <div class="menu">
                        <button class="button link" @click="shopping = true; showShare = false; showSettings = false">Shopping Mode</button>
                        <button class="button link" @click="summary = !summary">Shopping summary</button>
                        <button v-if="level === 4" class="button link" @click="showSettings = !showSettings">List settings</button>
                        <button v-if="level === 4" class="button danger" :disabled="!online || deletingList" @click="deleteList">Delete list</button>
                        <a class="button link" href="/lists">Offline lists &amp; installation</a>
                    </div>
                </details>
                <button v-else class="button secondary" @click="shopping = false">Exit Shopping Mode</button>
            </div>
        </div>
        <p v-if="message" class="notice" role="alert">{{ message }}</p>
        <div v-if="blocked" class="notice error" role="alert">
            <strong>A change needs your attention.</strong>
            <p>{{ blocked.message }}</p>
            <div class="actions">
                <button v-if="canReapply" class="button secondary" @click="store.resolveConflict(true)">Apply my change over the latest version</button>
                <button class="button secondary" @click="store.downloadChanges">Download pending changes</button>
                <button class="button secondary" @click="store.resolveConflict(false)">Discard this change</button>
            </div>
        </div>
        <SharingPanel v-if="showShare" :state="state" :change="change" :labels="labels" :online="online" />
        <ListSettings v-if="showSettings" :state="state" :change="change" :online="online" />
        <section v-if="summary && !shopping" class="panel summary-panel">
            <h2>Shopping summary</h2>
            <dl class="metrics">
                <div v-for="(count, status) in counts" :key="status"><dt>{{ labels[status] || status }}</dt><dd>{{ count }}</dd></div>
                <div><dt>Estimated total</dt><dd>{{ totals[0].toFixed(2) }}</dd></div>
                <div><dt>Actual total</dt><dd>{{ totals[1].toFixed(2) }}</dd></div>
            </dl>
            <details v-if="level >= 2 && state.deleted?.length">
                <summary>Recently deleted items</summary>
                <ul class="management">
                    <li v-for="item in state.deleted.filter(item => !items.some(active => active.id === item.id))" :key="item.id">
                        {{ item.name }}
                        <button class="button secondary" :disabled="queue.some(entry => entry.operation.item_id === item.id)" @click="store.enqueue('restore', item.id, item.version, {}, item)">Restore</button>
                    </li>
                </ul>
            </details>
            <details>
                <summary>Recent activity</summary>
                <ul><li v-for="(entry, index) in state.activity" :key="index">{{ entry.actor }} · {{ entry.action.replaceAll('.', ' ') }} · {{ new Date(entry.created_at.replace(' ', 'T') + 'Z').toLocaleString() }}</li></ul>
            </details>
        </section>
        <div v-if="level >= 2 && !shopping" class="add-dock">
            <form class="add-form" :class="{ 'has-voice': voice }" @submit.prevent="add">
                <label class="sr-only" for="new-item">Item name</label>
                <input id="new-item" ref="nameInput" v-model="name" list="item-suggestions" maxlength="150" autocomplete="off" placeholder="What do you need?" required>
                <datalist id="item-suggestions"><option v-for="suggestion in cachedSuggestions" :key="suggestion" :value="suggestion" /></datalist>
                <button v-if="voice" type="button" class="button secondary voice" @click="dictate">{{ listening ? 'Cancel voice' : 'Speak' }}</button>
                <button class="button primary" :disabled="adding"><span aria-hidden="true">+</span> Add item</button>
            </form>
            <p v-if="listening || voiceMessage" class="tiny muted" role="status">{{ voiceMessage || 'Listening…' }}</p>
        </div>
        <div class="sr-only" role="status">{{ note }}</div>
        <div v-if="undo" class="notice undo">Deleted {{ undo.name }}. <button class="button secondary" @click="restoreItem">Undo</button></div>
        <section class="item-section pending-section">
            <h2><span aria-hidden="true">○</span> Pending <span class="count">{{ pendingItems.length }}</span></h2>
            <p v-if="!pendingItems.length" class="empty">{{ items.length ? 'Everything is checked off.' : 'No items yet.' }}</p>
            <ul v-else class="items">
                <ItemRow v-for="item in pendingItems" :key="item.id" :item="item" :list-id="listId" :labels="labels" :participants="state.participants" :can-toggle="level >= 1" :shopping="shopping" @toggle="toggle(item)" @details="selected = item.id" />
            </ul>
        </section>
        <section class="item-section completed-section">
            <h2><span aria-hidden="true">✓</span> Completed <span class="count">{{ completed.length }}</span></h2>
            <p v-if="!completed.length" class="empty">Completed items will appear here.</p>
            <ul v-else class="items">
                <ItemRow v-for="item in completed" :key="item.id" :item="item" :list-id="listId" :labels="labels" :participants="state.participants" :can-toggle="level >= 1" :shopping="shopping" @toggle="toggle(item)" @details="selected = item.id" />
            </ul>
        </section>
        <ItemEditor v-if="selected && items.some(item => item.id === selected)" :key="selected" :item="items.find(item => item.id === selected)" :level="level" :participants="state.participants" :online="online" :enqueue="store.enqueue" :change="change" @close="selected = null" @delete="deleteItem" />
    </div>
    <div v-else class="panel">
        <h1>{{ starting ? 'Opening list…' : 'List unavailable' }}</h1>
        <p v-if="message" role="alert">{{ message }}</p>
        <p v-else-if="!starting">Open this list online first to save it on this device.</p>
        <button v-if="queue.length" class="button secondary" @click="store.downloadChanges">Download pending changes</button>
        <button v-if="blocked" class="button secondary" @click="store.resolveConflict(false)">Discard this change</button>
        <a class="text-link" href="/lists">Back to My lists</a>
    </div>
</template>
