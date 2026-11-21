import { database } from './storage'

document.querySelector('#logout')?.addEventListener('submit', async event => {
    const form = event.currentTarget
    event.preventDefault()
    try {
        if (await (await database()).count('operations')) {
            document.querySelector('#logout-notice').textContent = 'Open your lists and sync or resolve pending changes before signing out.'
            return
        }
        form.submit()
    } catch {
        document.querySelector('#logout-notice').textContent = 'Local changes could not be checked. Try again once this device can access its saved lists.'
    }
})
