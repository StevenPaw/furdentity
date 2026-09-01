import { ref } from 'vue'

const BASE_URL = '/api/v1'
// Deliberately NOT the actual access/refresh tokens – those live in httpOnly
// cookies the backend sets and this code never sees. This just mirrors
// whether a session exists, so the UI/router can react without a round trip.
const AUTH_FLAG_COOKIE = 'furdentity_auth'

function hasAuthFlagCookie() {
  return document.cookie.split('; ').some((c) => c.startsWith(`${AUTH_FLAG_COOKIE}=`))
}

const authenticated = ref(hasAuthFlagCookie())

export function isAuthenticated() {
  return authenticated.value
}

// Re-reads the auth-flag cookie after any call that may have changed it
// (login, refresh, logout, session revoke, account deletion) – the cookie
// itself is set/cleared by the backend's Set-Cookie header on that response.
function syncAuthState() {
  authenticated.value = hasAuthFlagCookie()
}

async function request(path, { method = 'GET', body, auth = false, _retried = false } = {}) {
  const response = await fetch(`${BASE_URL}${path}`, {
    method,
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: body === undefined ? undefined : JSON.stringify(body),
  })

  if (response.status === 401 && auth && !_retried && (await tryRefresh())) {
    return request(path, { method, body, auth, _retried: true })
  }

  const data = response.status === 204 ? null : await response.json().catch(() => null)

  if (!response.ok) {
    if (auth && response.status === 401) syncAuthState()
    throw new Error(data?.error?.message || `Request failed (${response.status})`)
  }

  return data
}

// The refresh token itself is an httpOnly cookie sent automatically by the
// browser – this call carries no body. Concurrent callers *in this tab*
// share a single in-flight refresh instead of each firing their own (the
// backend also tolerates a short grace window for genuinely concurrent
// refreshes from *other* tabs sharing the same cookie jar – see
// AuthController::refresh() – so this is purely a same-tab optimization now,
// not the only thing standing between a race and a bogus logout).
let refreshPromise = null

function tryRefresh() {
  if (refreshPromise) return refreshPromise

  refreshPromise = (async () => {
    try {
      const response = await fetch(`${BASE_URL}/auth/refresh`, {
        method: 'POST',
        credentials: 'same-origin',
      })
      if (!response.ok) throw new Error('refresh failed')
      syncAuthState()
      return true
    } catch {
      syncAuthState()
      return false
    }
  })()

  return refreshPromise.finally(() => {
    refreshPromise = null
  })
}

export const api = {
  ping: () => request('/public/ping'),
  publicProfiles: () => request('/public/profiles'),
  profileByHandle: (handle) => request(`/public/profile/${encodeURIComponent(handle)}`),
  requestLoginLink: (email, title, handle) =>
    request('/auth/request-link', { method: 'POST', body: { email, title, handle } }),
  confirmLogin: async (sid, code) => {
    const result = await request('/auth/confirm', { method: 'POST', body: { sid, code } })
    syncAuthState()
    return result
  },
  logout: async () => {
    try {
      await request('/internal/logout', { method: 'POST', auth: true })
    } finally {
      syncAuthState()
    }
  },
  me: () => request('/internal/me', { auth: true }),
  updateMe: (data) => request('/internal/me', { method: 'PATCH', body: data, auth: true }),
  deleteMe: async (handle) => {
    const result = await request('/internal/me', { method: 'DELETE', body: { handle }, auth: true })
    syncAuthState()
    return result
  },
  createLink: (data) => request('/internal/links', { method: 'POST', body: data, auth: true }),
  updateLink: (id, data) =>
    request(`/internal/links/${id}`, { method: 'PATCH', body: data, auth: true }),
  deleteLink: (id) => request(`/internal/links/${id}`, { method: 'DELETE', auth: true }),
  reorderLinks: (order) =>
    request('/internal/links/reorder', { method: 'POST', body: { order }, auth: true }),
  sessions: () => request('/internal/sessions', { auth: true }),
  revokeSession: async (id) => {
    const result = await request(`/internal/sessions/${id}`, { method: 'DELETE', auth: true })
    syncAuthState()
    return result
  },
  logoutAllSessions: async () => {
    const result = await request('/internal/sessions/logout-all', { method: 'POST', auth: true })
    syncAuthState()
    return result
  },
}

// Keeps `authenticated` in sync with another tab logging in/out/refreshing –
// cookies don't fire a 'storage' event, so tabs poll the flag on focus
// instead (cheap: it's just a document.cookie scan, no network call).
window.addEventListener('focus', syncAuthState)
document.addEventListener('visibilitychange', () => {
  if (document.visibilityState === 'visible') syncAuthState()
})
