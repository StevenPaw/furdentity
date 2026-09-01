<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { api } from '../api/client'

const router = useRouter()
const { t } = useI18n()

const sessions = ref([])
const error = ref('')
const busyId = ref(null)
const busyAll = ref(false)

const me = ref(null)
const showDeleteConfirm = ref(false)
const deleteConfirmInput = ref('')
const deleteError = ref('')
const deleting = ref(false)

async function load() {
  error.value = ''
  try {
    sessions.value = (await api.sessions()).data
  } catch (e) {
    error.value = e.message
  }
}

async function loadMe() {
  try {
    me.value = await api.me()
  } catch {
    // The sessions list above will surface auth problems; nothing extra to show here.
  }
}

async function revoke(id) {
  busyId.value = id
  try {
    await api.revokeSession(id)
    if (sessions.value.find((s) => s.id === id)?.current) {
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
    router.push({ name: 'login' })
  }
}

function startDelete() {
  showDeleteConfirm.value = true
  deleteConfirmInput.value = ''
  deleteError.value = ''
}

function cancelDelete() {
  showDeleteConfirm.value = false
  deleteConfirmInput.value = ''
  deleteError.value = ''
}

async function deleteProfile() {
  if (deleteConfirmInput.value !== me.value?.handle) {
    deleteError.value = t('settings.deleteMismatch')
    return
  }

  deleting.value = true
  deleteError.value = ''
  try {
    await api.deleteMe(deleteConfirmInput.value)
    router.push({ name: 'landing' })
  } catch (e) {
    deleteError.value = e.message
    deleting.value = false
  }
}

onMounted(() => {
  load()
  loadMe()
})
</script>

<template>
  <div class="container">
    <h1>{{ t('settings.heading') }}</h1>

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

    <div class="danger-zone">
      <h2>{{ t('settings.dangerZoneHeading') }}</h2>
      <p class="hint">{{ t('settings.deleteWarning') }}</p>

      <button v-if="!showDeleteConfirm" type="button" class="danger-btn" @click="startDelete">
        {{ t('settings.deleteProfile') }}
      </button>

      <template v-else>
        <label for="delete-confirm">
          {{ t('settings.deleteConfirmLabel', { handle: me?.handle }) }}
        </label>
        <input id="delete-confirm" v-model="deleteConfirmInput" type="text" autocomplete="off" />

        <p v-if="deleteError" style="color: crimson">{{ deleteError }}</p>

        <p class="danger-actions">
          <button type="button" @click="cancelDelete">{{ t('profile.cancel') }}</button>
          <button
            type="button"
            class="danger-btn"
            :disabled="deleting || deleteConfirmInput !== me?.handle"
            @click="deleteProfile"
          >
            {{ deleting ? t('login.submitting') : t('settings.deleteButton') }}
          </button>
        </p>
      </template>
    </div>
  </div>
</template>

<style scoped lang="scss" src="./SettingsView.scss"></style>
