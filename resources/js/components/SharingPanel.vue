<script setup>
import { ref } from 'vue'

const props = defineProps({ state: Object, change: Function, labels: Object, online: Boolean })
const role = ref('edit')
const name = ref('')
const days = ref(7)
const url = ref('')
const qr = ref('')
const error = ref('')
const status = ref('')
const busy = ref(false)
const roles = ['view', 'complete', 'edit', 'collaborate']
const nativeShare = Boolean(navigator.share)

async function run(action) {
    busy.value = true
    error.value = ''
    try {
        await action()
    } catch (failure) {
        if (failure.name !== 'AbortError') error.value = failure.message
    } finally {
        busy.value = false
    }
}

function generate() {
    return run(async () => {
        const result = await props.change('/invitations', { method: 'POST', data: { role: role.value, name: name.value, days: days.value } })
        url.value = result.url
        qr.value = ''
        status.value = 'Invitation created.'
    })
}

function copy() {
    return run(async () => {
        await navigator.clipboard.writeText(url.value)
        status.value = 'Link copied.'
    })
}

function showQr() {
    return run(async () => {
        if (qr.value) { qr.value = ''; return }
        const { default: QRCode } = await import('qrcode')
        qr.value = await QRCode.toDataURL(url.value, { width: 240, margin: 2 })
    })
}

function modify(person, selected) {
    if (!selected) return
    if (['owner', 'remove'].includes(selected) && !window.confirm(selected === 'owner' ? 'Transfer ownership to ' + person.name + '?' : 'Remove ' + person.name + ' from this list?')) return
    return run(() => props.change('/participants/' + person.id, { method: 'PATCH', data: { role: selected } }))
}
</script>

<template>
    <section class="panel share-panel" aria-labelledby="share-title">
        <h2 id="share-title">Share list</h2>
        <p v-if="!online">Reconnect to create or manage invitations.</p>
        <form class="stack" @submit.prevent="generate">
            <fieldset class="field-grid" :disabled="!online || busy">
                <label>Invitation permission
                    <select v-model="role"><option v-for="value in roles" :key="value" :value="value">{{ labels[value] || value }}</option></select>
                </label>
                <label>Link expires in
                    <select v-model="days"><option :value="1">1 day</option><option :value="7">7 days</option><option :value="30">30 days</option><option :value="90">90 days</option></select>
                </label>
                <label>Invitee name (optional) <input v-model="name" maxlength="60"></label>
            </fieldset>
            <button class="button primary" :disabled="!online || busy">Create invitation link</button>
        </form>
        <div v-if="url" class="stack invitation-result">
            <label>Invitation link <input :value="url" readonly @focus="$event.target.select()"></label>
            <div class="actions">
                <button class="button secondary" @click="copy">Copy link</button>
                <a class="button secondary" :href="'https://wa.me/?text=' + encodeURIComponent(url)" target="_blank" rel="noopener noreferrer">WhatsApp</a>
                <button class="button secondary" :aria-expanded="Boolean(qr)" @click="showQr">QR code</button>
                <button v-if="nativeShare" class="button secondary" @click="run(() => navigator.share({ title: state.list.name, url }))">Device sharing</button>
            </div>
            <img v-if="qr" :src="qr" width="240" height="240" alt="QR code for this invitation">
        </div>
        <p v-if="status" role="status">{{ status }}</p>
        <p v-if="error" class="notice error" role="alert">{{ error }}</p>
        <details v-if="state.invitations.length">
            <summary>Manage invitations</summary>
            <ul class="management">
                <li v-for="invitation in state.invitations" :key="invitation.id">
                    <span>{{ invitation.name || 'Invitation' }} · {{ labels[invitation.role] || invitation.role }}</span>
                    <span v-if="invitation.revoked_at" class="muted">Revoked</span>
                    <button v-else class="button secondary" :disabled="!online || busy" @click="run(() => change('/invitations/' + invitation.id, { method: 'DELETE' }))">Revoke link</button>
                </li>
            </ul>
        </details>
        <details v-if="state.actor.role === 'owner'">
            <summary>Manage participants</summary>
            <ul class="management">
                <li v-for="person in state.participants" :key="person.id">
                    <span>{{ person.name }} · {{ labels[person.role] || person.role }}</span>
                    <select v-if="person.id !== state.actor.id" :aria-label="'Change access for ' + person.name" :disabled="!online || busy" @change="modify(person, $event.target.value); $event.target.value = ''">
                        <option value="">Change access…</option>
                        <option v-for="value in roles" :key="value" :value="value">{{ labels[value] || value }}</option>
                        <option value="owner">Transfer ownership</option><option value="remove">Remove participant</option>
                    </select>
                </li>
            </ul>
        </details>
    </section>
</template>
