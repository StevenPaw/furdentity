<script setup>
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { isAuthenticated, clearTokens } from './api/client'
import { useI18n } from 'vue-i18n'
import { SUPPORTED_LOCALES, setLocale } from './i18n'

const router = useRouter()
const { t, locale } = useI18n()

function logout() {
  clearTokens()
  router.push({ name: 'login' })
}
</script>

<template>
  <header class="site-header">
    <RouterLink to="/" class="brand">{{ t('app.title') }}</RouterLink>
    <nav>
      <template v-if="isAuthenticated()">
        <RouterLink to="/app">{{ t('nav.myProfiles') }}</RouterLink>
        <RouterLink to="/app/sessions">{{ t('nav.sessions') }}</RouterLink>
        <button type="button" @click="logout">{{ t('nav.logout') }}</button>
      </template>
      <template v-else>
        <RouterLink to="/register">{{ t('nav.register') }}</RouterLink>
        <RouterLink to="/login">{{ t('nav.login') }}</RouterLink>
      </template>
      <select :value="locale" aria-label="Language" @change="setLocale($event.target.value)">
        <option v-for="l in SUPPORTED_LOCALES" :key="l" :value="l">{{ l.toUpperCase() }}</option>
      </select>
    </nav>
  </header>

  <main>
    <RouterView />
  </main>

  <footer class="site-footer">
    <div class="container footer-inner">
      <div class="footer-group">
        <span class="footer-heading">{{ t('footer.legal') }}</span>
        <RouterLink to="/legal/impressum">{{ t('footer.impressum') }}</RouterLink>
        <RouterLink to="/legal/datenschutz">{{ t('footer.privacy') }}</RouterLink>
      </div>
      <div class="footer-group">
        <RouterLink to="/about">{{ t('footer.about') }}</RouterLink>
      </div>
    </div>
  </footer>
</template>

<style scoped lang="scss" src="./App.scss"></style>
