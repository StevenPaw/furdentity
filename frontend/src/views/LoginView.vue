<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, storeTokens } from '../api/client'

const route = useRoute()
const router = useRouter()

const email = ref('')
const password = ref('')
const error = ref('')
const busy = ref(false)

async function submit() {
  busy.value = true
  error.value = ''
  try {
    storeTokens(await api.login(email.value, password.value))
    router.push(route.query.redirect || { name: 'home' })
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <h2>Login</h2>
  <form @submit.prevent="submit">
    <label for="email">E-Mail</label>
    <input id="email" v-model="email" type="text" autocomplete="username" />

    <label for="password">Passwort</label>
    <input id="password" v-model="password" type="password" autocomplete="current-password" />

    <p v-if="error" style="color: crimson">{{ error }}</p>

    <p style="margin-top: 1rem">
      <button type="submit" :disabled="busy">{{ busy ? '…' : 'Anmelden' }}</button>
    </p>
  </form>
</template>
