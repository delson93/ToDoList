<script setup>
import { ref } from 'vue'

const props = defineProps({ state: Object, change: Function, online: Boolean })
const name = ref(props.state.list.name)
const expiration = props.state.list.expires_at ? new Date(props.state.list.expires_at) : null
const expires = ref(expiration ? new Date(expiration.getTime() - expiration.getTimezoneOffset() * 60000).toISOString().slice(0, 16) : '')
const frequency = ref(props.state.recurrence?.frequency || '')
const interval = ref(props.state.recurrence?.interval_days || 7)
const shopper = ref('')
const status = ref('')
const busy = ref(false)

async function saveTemplate() {
    busy.value = true
    try {
        await props.change('/template', { method: 'POST' })
        status.value = 'Template saved. Choose it when creating your next list.'
    } catch (error) {
        status.value = error.message
    } finally {
        busy.value = false
    }
}

async function save() {
    busy.value = true
    try {
        await props.change('/settings', { method: 'PATCH', data: {
            name: name.value, expires_at: expires.value ? new Date(expires.value).toISOString() : null,
            frequency: frequency.value || null, interval_days: frequency.value === 'custom' ? interval.value : null,
            shopper: shopper.value || null,
        } })
        status.value = 'Settings saved.'
        shopper.value = ''
    } catch (error) {
        status.value = error.message
    } finally {
        busy.value = false
    }
}
</script>

<template>
    <section class="panel share-panel">
        <h2>List settings</h2>
        <p v-if="!online">Reconnect to update settings.</p>
        <form class="stack" @submit.prevent="save">
            <fieldset :disabled="!online || busy">
                <label>List name <input v-model="name" required maxlength="120"></label>
                <label>Expiration date (optional) <input v-model="expires" type="datetime-local"></label>
                <label>Recurrence
                    <select v-model="frequency"><option value="">None</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option><option value="custom">Custom</option></select>
                </label>
                <label v-if="frequency === 'custom'">Repeat every this many days <input v-model="interval" type="number" min="1" max="365" required></label>
                <label>Assign remaining shopping to
                    <select v-model="shopper"><option value="">Keep current assignments</option><option v-for="person in state.participants.filter(person => person.role !== 'view')" :key="person.id" :value="person.id">{{ person.name }}</option></select>
                </label>
            </fieldset>
            <button class="button primary" :disabled="!online || busy">Save settings</button>
        </form>
        <button v-if="state.can_save_template" class="button secondary" :disabled="!online || busy" @click="saveTemplate">Save as template</button>
        <p v-if="status" role="status">{{ status }}</p>
    </section>
</template>
