<script setup>
import { onMounted, ref } from 'vue'
import { api, isAuthenticated } from '../api/client'

const pingStatus = ref('…')
const profiles = ref([])
const error = ref('')

onMounted(async () => {
  try {
    const ping = await api.ping()
    pingStatus.value = ping.status

    const source = isAuthenticated() ? api.internalProfiles() : api.publicProfiles()
    profiles.value = (await source).data
  } catch (e) {
    error.value = e.message
  }
})
</script>

<template>
  <p>Public API: <strong>{{ pingStatus }}</strong></p>
  <p v-if="error" style="color: crimson">{{ error }}</p>

  <h2>Profile ({{ isAuthenticated() ? 'intern, alle' : 'öffentlich' }})</h2>
  <ul>
    <li v-for="profile in profiles" :key="profile.id">
      <strong>{{ profile.title }}</strong>
      <span v-if="profile.handle"> — {{ profile.handle }}</span>
    </li>
    <li v-if="!profiles.length">Noch keine Profile.</li>
  </ul>
</template>
