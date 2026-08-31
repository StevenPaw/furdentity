<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { api, clearTokens } from '../api/client'

const router = useRouter()
const { t } = useI18n()

const sessions = ref([])
const error = ref('')
const busyId = ref(null)
const busyAll = ref(false)

async function load() {
  error.value = ''
  try {
    sessions.value = (await api.sessions()).data
  } catch (e) {
    error.value = e.message
  }
}

async function revoke(id) {
  busyId.value = id
  try {
    await api.revokeSession(id)
    if (sessions.value.find((s) => s.id === id)?.current) {
      clearTokens()
      router.push({ name: 'login' })
      return
    }
    await load()
  } catch (e) {
    error.value = e.message
  } finally {
    busyId.value = null
  }
}

async function logoutEverywhere() {
  busyAll.value = true
  try {
    await api.logoutAllSessions()
  } catch {
    // The session that made this call is revoked too either way – proceed to logout locally.
  } finally {
    clearTokens()
    router.push({ name: 'login' })
  }
}

onMounted(load)
</script>

<template>
  <div class="container">
    <h2>{{ t('sessions.heading') }}</h2>
    <p v-if="error" style="color: crimson">{{ error }}</p>

    <ul class="sessions-list">
      <li v-for="session in sessions" :key="session.id" class="session-row">
        <div>
          <strong>{{ session.userAgent || t('sessions.unknownDevice') }}</strong>
          <span v-if="session.current" class="badge">{{ t('sessions.thisDevice') }}</span>
          <div class="meta">
            {{ session.ipAddress }} · {{ t('sessions.lastActive') }} {{ session.lastUsedAt }}
          </div>
        </div>
        <button type="button" :disabled="busyId === session.id" @click="revoke(session.id)">
          {{ t('sessions.revoke') }}
        </button>
      </li>
      <li v-if="!sessions.length && !error">{{ t('sessions.none') }}</li>
    </ul>

    <p style="margin-top: 1.5rem">
      <button type="button" :disabled="busyAll" @click="logoutEverywhere">
        {{ t('sessions.logoutAll') }}
      </button>
    </p>
  </div>
</template>

<style scoped lang="scss" src="./SessionsView.scss"></style>
