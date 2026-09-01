import { ref } from 'vue'

const BASE_URL = '/api/v1'
// Deliberately NOT the actual session token – that lives in an httpOnly
// cookie the backend sets and this code never sees. This just mirrors
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
// (login, logout, session revoke, account deletion, or an authenticated
// call finding the session dead) – the cookie itself is set/cleared by the
// backend's Set-Cookie header on that response.
function syncAuthState() {
  authenticated.value = hasAuthFlagCookie()
}

// No refresh/retry dance: the session cookie is a single long-lived token
// that the backend silently re-issues (fresh expiry) on every authenticated
// request (see InternalApiController::init()) – it's automatically sent by
// the browser like any cookie, so simply calling an endpoint is enough to
// both authenticate and keep the session alive.
async function request(path, { method = 'GET', body } = {}) {
  const response = await fetch(`${BASE_URL}${path}`, {
    method,
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: body === undefined ? undefined : JSON.stringify(body),
  })

  const data = response.status === 204 ? null : await response.json().catch(() => null)

  if (!response.ok) {
    if (response.status === 401) syncAuthState()
    throw new Error(data?.error?.message || `Request failed (${response.status})`)
  }

  return data
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
      await request('/internal/logout', { method: 'POST' })
    } finally {
      syncAuthState()
    }
  },
  me: () => request('/internal/me'),
  updateMe: (data) => request('/internal/me', { method: 'PATCH', body: data }),
  deleteMe: async (handle) => {
    const result = await request('/internal/me', { method: 'DELETE', body: { handle } })
    syncAuthState()
    return result
  },
  uploadAvatar: (imageDataUrl) =>
    request('/internal/me/avatar', { method: 'POST', body: { image: imageDataUrl } }),
  uploadBackground: (imageDataUrl) =>
    request('/internal/me/background', { method: 'POST', body: { image: imageDataUrl } }),
  createLink: (data) => request('/internal/links', { method: 'POST', body: data }),
  updateLink: (id, data) => request(`/internal/links/${id}`, { method: 'PATCH', body: data }),
  deleteLink: (id) => request(`/internal/links/${id}`, { method: 'DELETE' }),
  reorderLinks: (order) => request('/internal/links/reorder', { method: 'POST', body: { order } }),
  sessions: () => request('/internal/sessions'),
  revokeSession: async (id) => {
    const result = await request(`/internal/sessions/${id}`, { method: 'DELETE' })
    syncAuthState()
    return result
  },
  logoutAllSessions: async () => {
    const result = await request('/internal/sessions/logout-all', { method: 'POST' })
    syncAuthState()
    return result
  },
}

// Keeps `authenticated` in sync with another tab logging in/out – cookies
// don't fire a 'storage' event, so tabs poll the flag on focus instead
// (cheap: it's just a document.cookie scan, no network call).
window.addEventListener('focus', syncAuthState)
document.addEventListener('visibilitychange', () => {
  if (document.visibilityState === 'visible') syncAuthState()
})
