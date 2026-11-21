import { createApp } from 'vue'
import { registerWorker } from './pwa'
import App from './components/App.vue'

const root = document.querySelector('#app')
if (root) {
    if (['light', 'dark'].includes(root.dataset.theme)) document.documentElement.dataset.theme = root.dataset.theme
    createApp(App, { ...root.dataset }).mount(root)
    registerWorker()
}
