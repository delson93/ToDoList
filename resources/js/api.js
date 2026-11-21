let csrf = null

export class ApiError extends Error {
    constructor(response, body) {
        super(body?.message || `Request failed (${response.status}).`)
        this.status = response.status
    }
}

export async function api(path, { method = 'GET', data, etag, retry = true } = {}) {
    const headers = { Accept: 'application/json' }
    if (etag) headers['If-None-Match'] = etag
    if (method !== 'GET') {
        csrf ??= (await api('/api/session')).body.csrf
        headers['X-CSRF-TOKEN'] = csrf
        if (!(data instanceof FormData)) headers['Content-Type'] = 'application/json'
    }
    const response = await fetch(path, {
        method,
        headers,
        credentials: 'same-origin',
        cache: 'no-store',
        body: data instanceof FormData ? data : JSON.stringify(data),
    })
    if (response.status === 419 && method !== 'GET' && retry) {
        csrf = null
        return api(path, { method, data, etag, retry: false })
    }
    const body = response.headers.get('Content-Type')?.includes('application/json') ? await response.json() : null
    if (!response.ok && response.status !== 304) throw new ApiError(response, body)
    return { body, etag: response.headers.get('ETag') }
}
