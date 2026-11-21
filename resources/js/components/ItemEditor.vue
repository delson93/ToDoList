<script setup>
import { onMounted, ref } from 'vue'

const props = defineProps({ item: Object, level: Number, participants: Array, online: Boolean, enqueue: Function, change: Function })
const emit = defineEmits(['close', 'delete'])
const dialog = ref(null)
const error = ref('')
const file = ref(null)
const comment = ref('')
const busy = ref(false)
const details = ['name', 'description', 'quantity', 'unit', 'estimated_price', 'actual_price', 'priority', 'assigned_to', 'notes', 'alternative', 'due_date', 'status', 'purchased']
const fields = ref(Object.fromEntries(details.map(key => [key, props.item[key] ?? ''])))
const base = props.item.version

onMounted(() => dialog.value?.showModal())

async function save() {
    const permitted = props.level >= 2 ? details : ['status', 'purchased']
    const changed = Object.fromEntries(permitted.filter(key => String(fields.value[key]) !== String(props.item[key] ?? '')).map(key => [key, fields.value[key] === '' ? null : fields.value[key]]))
    if (changed.status === 'partial' && !(Number(fields.value.purchased) > 0 && Number(fields.value.purchased) < Number(fields.value.quantity))) {
        error.value = 'Enter a purchased amount smaller than the requested quantity.'
        return
    }
    if (Object.keys(changed).length) {
        if (props.level === 1) changed.status = fields.value.status
        const ok = await props.enqueue(props.level >= 2 ? 'edit' : 'status', props.item.id, base, changed)
        if (!ok) return
    }
    emit('close')
}

async function upload() {
    if (!file.value || busy.value) return
    busy.value = true
    error.value = ''
    const data = new FormData()
    data.append('photo', file.value)
    try {
        await props.change('/items/' + props.item.id + '/photo', { method: 'POST', data })
        file.value = null
    } catch (failure) {
        error.value = failure.message
    } finally {
        busy.value = false
    }
}

async function addComment() {
    if (await props.enqueue('comment', props.item.id, null, { body: comment.value })) {
        comment.value = ''
        emit('close')
    }
}
</script>

<template>
    <dialog ref="dialog" class="editor" aria-labelledby="editor-title" @close="$emit('close')">
        <div class="section-head"><h2 id="editor-title">Item details</h2><button class="button secondary" @click="$emit('close')">Close</button></div>
        <form class="stack" @submit.prevent="save">
            <fieldset :disabled="level < 2">
                <label>Item name <input v-model="fields.name" required maxlength="150"></label>
                <label>Description <textarea v-model="fields.description" maxlength="1500"></textarea></label>
                <div class="field-grid">
                    <label>Quantity <input v-model="fields.quantity" type="number" min="0.001" step="0.001" max="9999999"></label>
                    <label>Unit <input v-model="fields.unit" maxlength="32" placeholder="kg, cartons, pieces…"></label>
                    <label>Estimated price <input v-model="fields.estimated_price" type="number" min="0" max="99999999" step="0.01"></label>
                    <label>Actual price <input v-model="fields.actual_price" type="number" min="0" max="99999999" step="0.01"></label>
                    <label>Priority <select v-model="fields.priority"><option value="">None</option><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option></select></label>
                    <label>Assigned to <select v-model="fields.assigned_to"><option value="">Anyone</option><option v-for="person in participants" :key="person.id" :value="person.id">{{ person.name }}</option></select></label>
                </div>
                <label>Alternative product <input v-model="fields.alternative" maxlength="150"></label>
                <label>Due date <input v-model="fields.due_date" type="date"></label>
                <label>Notes <textarea v-model="fields.notes" maxlength="1500"></textarea></label>
            </fieldset>
            <fieldset :disabled="level < 1" class="field-grid">
                <label>Status <select v-model="fields.status"><option value="pending">Pending</option><option value="completed">Completed</option><option value="unavailable">Unavailable</option><option value="partial">Partially purchased</option></select></label>
                <label v-if="fields.status === 'partial'">Purchased amount <input v-model="fields.purchased" type="number" min="0.001" max="9999999" step="0.001" required></label>
            </fieldset>
            <button v-if="level >= 1" class="button primary">Save changes</button>
        </form>
        <p v-if="error" class="notice error" role="alert">{{ error }}</p>
        <details v-if="level >= 2">
            <summary>Product photo</summary>
            <form class="stack" @submit.prevent="upload">
                <label>JPEG, PNG, or WebP, up to 5 MB <input type="file" accept="image/jpeg,image/png,image/webp" :disabled="!online" @change="file = $event.target.files[0]"></label>
                <button class="button secondary" :disabled="!online || busy || !file">Upload photo</button>
                <span v-if="!online">Connect to upload a photo.</span>
            </form>
        </details>
        <details>
            <summary>Comments ({{ item.comments?.length || 0 }})</summary>
            <ul class="comments"><li v-for="entry in item.comments" :key="entry.id"><strong>{{ entry.author }}</strong><p>{{ entry.body }}</p></li></ul>
            <form v-if="level >= 3" class="stack" @submit.prevent="addComment">
                <label>Add a comment <textarea v-model="comment" required maxlength="1000"></textarea></label>
                <button class="button secondary">Post comment</button>
            </form>
        </details>
        <button v-if="level >= 2" class="button danger" @click="$emit('delete', item)">Delete item</button>
    </dialog>
</template>
