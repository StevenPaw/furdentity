<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { api } from '../api/client'

const props = defineProps({
  sid: {
    type: Number,
    required: true,
  },
})

const router = useRouter()
const { t } = useI18n()

const code = ref('')
const error = ref('')
const busy = ref(false)

async function submit() {
  busy.value = true
  error.value = ''
  try {
    const result = await api.confirmLogin(props.sid, code.value)
    router.push({ name: 'profile', params: { handle: result.handle } })
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <form @submit.prevent="submit">
    <label for="manual-code">{{ t('login.codeLabel') }}</label>
    <input
      id="manual-code"
      v-model="code"
      type="text"
      inputmode="numeric"
      autocomplete="one-time-code"
      required
      maxlength="6"
    />

    <p v-if="error" style="color: crimson">{{ error }}</p>

    <p style="margin-top: 1rem">
      <button type="submit" :disabled="busy">
        {{ busy ? t('login.codeSubmitting') : t('login.codeSubmit') }}
      </button>
    </p>
  </form>
</template>
