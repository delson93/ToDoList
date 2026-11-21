<script setup>
import { computed, defineAsyncComponent, onMounted, ref } from 'vue'
import { api } from '../api'
import { database } from '../storage'
import { installPrompt, installed } from '../pwa'

const ListScreen = defineAsyncComponent(() => import('./ListScreen.vue'))
const props = defineProps({ screen: String, listId: String, reverb: String, labels: String })
const path = location.pathname.match(/^\/lists\/([a-f0-9-]{36})$/i)
const recoveryId = new URLSearchParams(location.search).get('list')
const validRecovery = /^[a-f0-9-]{36}$/i.test(recoveryId || '') ? recoveryId : null
const activeId = props.listId || (props.screen === 'offline' ? path?.[1] || validRecovery : null)
const offlineLibrary = computed(() => props.screen === 'offline' && !activeId)
const saved = ref([])
const unsynced = ref([])
const name = ref('')
const token = ref('')
const error = ref('')
const busy = ref(false)
const instructions = ref(false)

onMounted(async () => {
    if (props.screen === 'join') {
        token.value = location.hash.slice(1)
        history.replaceState(null, '', location.pathname)
    }
    if (offlineLibrary.value || props.screen === 'library') {
        try {
            const db = await database()
            unsynced.value = [...new Set((await db.getAll('operations')).map(entry => entry.listId))]
            saved.value = (await db.getAll('lists')).filter(({ list, offline }) =>
                offline && (!list.expires_at || Date.parse(list.expires_at) > Date.now()))
        } catch {
            error.value = 'Saved lists could not be loaded on this device.'
        }
    }
})

async function join() {
    busy.value = true
    error.value = ''
    try {
        const { body } = await api('/api/invitations/accept', { method: 'POST', data: { token: token.value, name: name.value } })
        location.assign(body.url)
    } catch (failure) {
        error.value = failure.status ? failure.message : 'Connect to the internet to accept this invitation.'
    } finally {
        busy.value = false
    }
}

async function install() {
    if (installPrompt.value) {
        const prompt = installPrompt.value
        installPrompt.value = null
        await prompt.prompt()
    } else {
        instructions.value = !instructions.value
    }
}
</script>

<template>
    <ListScreen v-if="activeId" :list-id="activeId" :reverb="reverb" :labels="labels" />
    <div v-else-if="screen === 'join'" class="panel">
        <h1>Join a shared list</h1>
        <form v-if="token" class="stack" @submit.prevent="join">
            <label>Your display name (optional) <input v-model="name" maxlength="60" autocomplete="nickname"></label>
            <button class="button primary" :disabled="busy">{{ busy ? 'Joining…' : 'Open list' }}</button>
        </form>
        <p v-else>Open the complete invitation link sent by the list owner.</p>
        <p v-if="error" class="notice error" role="alert">{{ error }}</p>
    </div>
    <section v-else class="library-tools">
        <h1 v-if="offlineLibrary">My offline lists</h1>
        <h2 v-else-if="saved.length">Available offline</h2>
        <ul v-if="saved.length" class="library">
            <li v-for="{ list } in saved" :key="list.id"><a :href="'/lists/' + list.id">{{ list.name }} <span aria-hidden="true">→</span></a></li>
        </ul>
        <p v-else-if="offlineLibrary">Open a list while connected to save it for offline use.</p>
        <details v-if="unsynced.length">
            <summary>Recover unsynced changes</summary>
            <ul class="library">
                <li v-for="id in unsynced" :key="id"><a :href="'/offline?list=' + id">{{ saved.find(entry => entry.list.id === id)?.list.name || 'Open saved changes' }}</a></li>
            </ul>
        </details>
        <p v-if="error" role="alert">{{ error }}</p>
        <button v-if="!installed" class="button secondary" :aria-expanded="instructions" @click="install">Install app</button>
        <div v-if="instructions" class="notice">
            <p><strong>iPhone or iPad:</strong> In Safari, tap Share, then Add to Home Screen.</p>
            <p><strong>Android:</strong> In Chrome, open the menu and choose Install app or Add to Home screen.</p>
            <p><strong>Desktop:</strong> Look for the install icon in the address bar or the browser menu.</p>
            <p>To save a specific list, use your browser’s bookmark or home-screen shortcut while the list is open. Removing a shortcut leaves the list intact.</p>
        </div>
    </section>
</template>
