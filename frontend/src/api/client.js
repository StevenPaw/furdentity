const BASE_URL = '/api/v1'
const TOKEN_KEY = 'furdentity.token'
const REFRESH_KEY = 'furdentity.refreshToken'

export function isAuthenticated() {
  return Boolean(localStorage.getItem(TOKEN_KEY))
}

export function storeTokens({ token, refreshToken }) {
  localStorage.setItem(TOKEN_KEY, token)
  if (refreshToken) localStorage.setItem(REFRESH_KEY, refreshToken)
}

export function clearTokens() {
  localStorage.removeItem(TOKEN_KEY)
  localStorage.removeItem(REFRESH_KEY)
}

async function request(path, { method = 'GET', body, auth = false, _retried = false } = {}) {
  const headers = { 'Content-Type': 'application/json' }

  if (auth) {
    const token = localStorage.getItem(TOKEN_KEY)
    if (token) headers.Authorization = `Bearer ${token}`
  }

  const response = await fetch(`${BASE_URL}${path}`, {
    method,
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
  })

  if (response.status === 401 && auth && !_retried && (await tryRefresh())) {
    return request(path, { method, body, auth, _retried: true })
  }

  const data = response.status === 204 ? null : await response.json().catch(() => null)

  if (!response.ok) {
    throw new Error(data?.error?.message || `Request failed (${response.status})`)
  }

  return data
}

async function tryRefresh() {
  const refreshToken = localStorage.getItem(REFRESH_KEY)
  if (!refreshToken) return false

  try {
    const response = await fetch(`${BASE_URL}/auth/refresh`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ refreshToken }),
    })
    if (!response.ok) throw new Error('refresh failed')
    storeTokens(await response.json())
    return true
  } catch {
    clearTokens()
    return false
  }
}

export const api = {
  ping: () => request('/public/ping'),
  publicProfiles: () => request('/public/profiles'),
  login: (email, password) =>
    request('/auth/login', { method: 'POST', body: { email, password } }),
  me: () => request('/internal/me', { auth: true }),
  internalProfiles: () => request('/internal/profiles', { auth: true }),
}
