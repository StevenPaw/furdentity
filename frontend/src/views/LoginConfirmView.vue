<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { api, storeTokens } from '../api/client'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const state = ref('pending') // 'pending' | 'error'
const error = ref('')

onMounted(async () => {
  const sid = Number(route.query.sid)
  const code = String(route.query.code || '')

  if (!sid || !code) {
    state.value = 'error'
    error.value = t('loginConfirm.invalid')
    return
  }

  try {
    storeTokens(await api.confirmLogin(sid, code))
    router.push({ name: 'app-home' })
  } catch (e) {
    state.value = 'error'
    error.value = e.message
  }
})
</script>

<template>
  <div class="container">
    <h2>{{ t('loginConfirm.heading') }}</h2>

    <p v-if="state === 'pending'">{{ t('loginConfirm.pending') }}</p>
    <template v-else>
      <p style="color: crimson">{{ error }}</p>
      <p>
        <RouterLink to="/login">{{ t('loginConfirm.backToLogin') }}</RouterLink>
      </p>
    </template>
  </div>
</template>
