<script setup>
import { computed, onUnmounted, ref, watch } from 'vue'
import { database } from '../storage'

const props = defineProps({ item: Object, listId: String, labels: Object, participants: Array, canToggle: Boolean, shopping: Boolean })
defineEmits(['toggle', 'details'])
const photo = ref('')
let objectUrl
let photoRequest = 0
const assignee = computed(() => props.participants.find(participant => participant.id === props.item.assigned_to)?.name)
const amount = computed(() => {
    if (!props.item.quantity) return ''
    const quantity = Number(props.item.quantity)
    const unit = props.item.unit ? ' ' + props.item.unit : ''
    if (props.item.status !== 'partial') return quantity + unit
    return `${Number((quantity - Number(props.item.purchased)).toFixed(3))}${unit} left · ${Number(props.item.purchased)} purchased`
})

watch(() => props.item.photo, async url => {
    const request = ++photoRequest
    if (objectUrl) URL.revokeObjectURL(objectUrl)
    objectUrl = null
    photo.value = ''
    if (!url) return
    try {
        const entry = await (await database()).get('photos', [props.listId, url])
        if (request !== photoRequest) return
        if (entry) objectUrl = URL.createObjectURL(entry.blob)
        photo.value = objectUrl || (navigator.onLine ? url : '')
    } catch {
        if (request === photoRequest && navigator.onLine) photo.value = url
    }
}, { immediate: true })

onUnmounted(() => { photoRequest++; if (objectUrl) URL.revokeObjectURL(objectUrl) })
</script>

<template>
    <li class="item-row" :class="{ 'is-completed': item.status === 'completed' }">
        <label class="item-toggle" :class="{ 'can-toggle': canToggle }">
            <input :id="'check-' + item.id" type="checkbox" :checked="item.status === 'completed'" :disabled="!canToggle" :aria-label="(item.status === 'completed' ? 'Restore ' : 'Complete ') + item.name" @click.prevent="$emit('toggle')">
            <img v-if="photo" :src="photo" width="48" height="48" alt="" class="item-photo" loading="lazy">
            <span class="item-copy">
                <span class="item-name">{{ item.name }}</span>
                <span v-if="amount || assignee || item.due_date" class="tiny muted">{{ [amount, assignee, item.due_date].filter(Boolean).join(' · ') }}</span>
            </span>
        </label>
        <span v-if="item.priority && item.status !== 'completed'" class="chip" :class="'priority-' + item.priority">{{ item.priority }} priority</span>
        <span v-if="item.status === 'unavailable' || item.status === 'partial'" class="chip">{{ labels[item.status] || (item.status === 'partial' ? 'Partial' : 'Unavailable') }}</span>
        <button v-if="!shopping" class="button link item-details" :aria-label="'Details for ' + item.name" @click="$emit('details')">Details</button>
    </li>
</template>
