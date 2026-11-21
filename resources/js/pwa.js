import { ref } from 'vue'

export const installPrompt = ref(null)
export const installed = ref(matchMedia('(display-mode: standalone)').matches || navigator.standalone === true)
let registration

window.addEventListener('beforeinstallprompt', event => {
    event.preventDefault()
    installPrompt.value = event
})
window.addEventListener('appinstalled', () => {
    installed.value = true
    installPrompt.value = null
})

export function registerWorker() {
    if (!('serviceWorker' in navigator) || import.meta.env.DEV) return Promise.resolve(null)
    return registration ??= navigator.serviceWorker.register('/sw.js').catch(() => null)
}

export async function shellReady() {
    if (!await registerWorker()) return false
    return new Promise(resolve => {
        const timeout = setTimeout(() => resolve(false), 6000)
        navigator.serviceWorker.ready.then(({ active }) => {
            const channel = new MessageChannel()
            channel.port1.onmessage = event => {
                clearTimeout(timeout)
                channel.port1.close()
                resolve(event.data === 'ready')
            }
            active.postMessage('offline-ready', [channel.port2])
        })
    })
}
