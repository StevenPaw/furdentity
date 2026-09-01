<script setup>
import { ref } from 'vue'
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { api, isAuthenticated } from './api/client'
import { useI18n } from 'vue-i18n'
import logoWhite from './assets/Furdentity-Logo-White.svg'
import profileIcon from './assets/icons/profile.svg'
import settingsIcon from './assets/icons/settings.svg'
import languageIcon from './assets/icons/language.svg'
import logoutIcon from './assets/icons/logout.svg'
import loginIcon from './assets/icons/login.svg'
import signupIcon from './assets/icons/signup.svg'
import LanguageModal from './components/LanguageModal.vue'

const router = useRouter()
const { t } = useI18n()

const showLanguageModal = ref(false)

async function logout() {
  try {
    await api.logout()
  } finally {
    // Best-effort either way – if the request itself failed (e.g. offline),
    // the cookies may still be sitting there, but there's nothing more the
    // client can do about httpOnly cookies from here, so just navigate on.
    router.push({ name: 'login' })
  }
}
</script>

<template>
  <header class="site-header">
    <RouterLink to="/" class="brand">
      <img :src="logoWhite" alt="" class="brand-logo" />
      <span>{{ t('app.title') }}</span>
    </RouterLink>
    <nav>
      <template v-if="isAuthenticated()">
        <RouterLink to="/app" class="icon-link" :aria-label="t('nav.myProfile')" :title="t('nav.myProfile')">
          <img :src="profileIcon" alt="" />
        </RouterLink>
        <RouterLink to="/settings" class="icon-link" :aria-label="t('nav.settings')" :title="t('nav.settings')">
          <img :src="settingsIcon" alt="" />
        </RouterLink>
        <button
          type="button"
          class="icon-link"
          :aria-label="t('nav.language')"
          :title="t('nav.language')"
          @click="showLanguageModal = true"
        >
          <img :src="languageIcon" alt="" />
        </button>
        <button
          type="button"
          class="icon-link"
          :aria-label="t('nav.logout')"
          :title="t('nav.logout')"
          @click="logout"
        >
          <img :src="logoutIcon" alt="" />
        </button>
      </template>
      <template v-else>
        <RouterLink to="/register" class="icon-link" :aria-label="t('nav.register')" :title="t('nav.register')">
          <img :src="signupIcon" alt="" />
        </RouterLink>
        <RouterLink to="/login" class="icon-link" :aria-label="t('nav.login')" :title="t('nav.login')">
          <img :src="loginIcon" alt="" />
        </RouterLink>
        <button
          type="button"
          class="icon-link"
          :aria-label="t('nav.language')"
          :title="t('nav.language')"
          @click="showLanguageModal = true"
        >
          <img :src="languageIcon" alt="" />
        </button>
      </template>
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

  <LanguageModal v-if="showLanguageModal" @close="showLanguageModal = false" />
</template>

<style scoped lang="scss" src="./App.scss"></style>
