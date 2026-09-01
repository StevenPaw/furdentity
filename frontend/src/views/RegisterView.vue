<script setup>
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { api } from '../api/client'

const { t } = useI18n()

const email = ref('')
const title = ref('')
const handle = ref('')
const error = ref('')
const busy = ref(false)
const sent = ref(false)

async function submit() {
  busy.value = true
  error.value = ''
  try {
    await api.requestLoginLink(email.value, title.value, handle.value)
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
    <h2>{{ t('register.heading') }}</h2>

    <template v-if="sent">
      <p>{{ t('login.linkSent', { email }) }}</p>
    </template>
    <template v-else>
      <p>{{ t('register.intro') }}</p>
      <form @submit.prevent="submit">
        <label for="email">{{ t('login.email') }}</label>
        <input id="email" v-model="email" type="email" autocomplete="email" required />

        <label for="title">{{ t('register.title') }}</label>
        <input id="title" v-model="title" type="text" autocomplete="nickname" required maxlength="30" />
        <p class="hint">{{ title.length }}/30 {{ t('profile.characters') }} · {{ t('register.titleHint') }}</p>

        <label for="handle">{{ t('register.username') }}</label>
        <p class="url-preview">furdentity.com/id/<input
          id="handle"
          v-model="handle"
          type="text"
          pattern="[a-z0-9_\-]{3,32}"
          autocomplete="off"
          required
        /></p>
        <p class="hint">{{ t('register.usernameHint') }}</p>

        <p v-if="error" style="color: crimson">{{ error }}</p>

        <p style="margin-top: 1rem">
          <button type="submit" :disabled="busy">
            {{ busy ? t('login.submitting') : t('register.submit') }}
          </button>
        </p>
      </form>

      <p>
        {{ t('register.haveAccount') }}
        <RouterLink to="/login">{{ t('nav.login') }}</RouterLink>
      </p>
    </template>
  </div>
</template>

<style scoped lang="scss" src="./RegisterView.scss"></style>
