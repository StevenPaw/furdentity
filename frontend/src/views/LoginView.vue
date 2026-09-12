<script setup>
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { api } from '../api/client'
import ManualCodeEntry from '../components/ManualCodeEntry.vue'

const { t } = useI18n()

const email = ref('')
const error = ref('')
const busy = ref(false)
const sent = ref(false)
const sid = ref(null)

async function submit() {
  busy.value = true
  error.value = ''
  try {
    const result = await api.requestLoginLink(email.value)
    sid.value = result.sid
    sent.value = true
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="container">
    <h2>{{ t('login.heading') }}</h2>

    <template v-if="sent">
      <p>{{ t('login.linkSent', { email }) }}</p>
      <p>{{ t('login.codeIntro') }}</p>
      <ManualCodeEntry :sid="sid" />
    </template>
    <template v-else>
      <p>{{ t('login.intro') }}</p>
      <form @submit.prevent="submit">
        <label for="email">{{ t('login.email') }}</label>
        <input id="email" v-model="email" type="email" autocomplete="email" required />

        <p v-if="error" style="color: crimson">{{ error }}</p>

        <p style="margin-top: 1rem">
          <button type="submit" :disabled="busy">
            {{ busy ? t('login.submitting') : t('login.submit') }}
          </button>
        </p>
      </form>

      <hr>

      <p>
        {{ t('login.noAccount') }}
        <RouterLink to="/register">{{ t('nav.register') }}</RouterLink>
      </p>
    </template>
  </div>
</template>
