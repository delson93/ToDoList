import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { api } from './api'

export function connect(key, listId, participantId, update) {
    window.Pusher = Pusher
    const echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: location.hostname,
        wsPort: Number(location.port || (location.protocol === 'https:' ? 443 : 80)),
        wssPort: Number(location.port || 443),
        forceTLS: location.protocol === 'https:',
        enabledTransports: ['ws', 'wss'],
        channelAuthorization: {
            customHandler: async (parameters, callback) => {
                try {
                    const { body } = await api('/api/broadcasting/auth', {
                        method: 'POST',
                        data: { channel_name: parameters.channelName, socket_id: parameters.socketId },
                    })
                    callback(null, body)
                } catch (error) {
                    callback(error, null)
                }
            },
        },
    })
    echo.private(`lists.${listId}.${participantId}`).listen('.list.changed', update)
    return echo
}
