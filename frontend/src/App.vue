<script setup>
import { RouterLink, RouterView } from 'vue-router'
import { isAuthenticated, clearTokens } from './api/client'
import { useRouter } from 'vue-router'

const router = useRouter()

function logout() {
  clearTokens()
  router.push({ name: 'login' })
}
</script>

<template>
  <header>
    <h1>Furdentity</h1>
    <nav>
      <RouterLink to="/">Start</RouterLink>
      <template v-if="isAuthenticated()">
        · <button type="button" @click="logout">Logout</button>
      </template>
      <template v-else> · <RouterLink to="/login">Login</RouterLink> </template>
    </nav>
  </header>

  <main>
    <RouterView />
  </main>
</template>

<style scoped>
header {
  border-bottom: 1px solid #8884;
  margin-bottom: 1.5rem;
}
nav {
  display: flex;
  gap: 0.4rem;
  align-items: center;
  padding-bottom: 1rem;
}
</style>
