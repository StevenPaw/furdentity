<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import logo from '../assets/Furdentity-Logo-White.svg'
import ProfileCarousel from '../components/ProfileCarousel.vue'
import { api, isAuthenticated } from '../api/client'

const { t, tm } = useI18n()

const ctaTo = ref({ name: 'register' })
const ctaLabel = ref(t('landing.cta'))

onMounted(async () => {
  if (!isAuthenticated()) return

  try {
    const me = await api.me()
    ctaTo.value = { name: 'profile', params: { handle: me.handle } }
    ctaLabel.value = t('landing.ctaLoggedIn')
  } catch {
    // Auth-flag cookie says logged in but the session is actually dead –
    // keep the default "create a profile" CTA.
  }
})
</script>

<template>
  <div class="landing">
    <section class="hero">
      <img :src="logo" :alt="t('app.title')" class="hero-logo" />
      <h1>{{ t('landing.heroTitle') }}</h1>
      <p class="hero-subtitle">{{ t('landing.heroSubtitle') }}</p>
      <RouterLink :to="ctaTo" class="cta">{{ ctaLabel }}</RouterLink>
    </section>

    <ProfileCarousel />

    <section class="features container">
      <div v-for="feature in tm('landing.features')" :key="feature.title" class="feature">
        <h3>{{ feature.title }}</h3>
        <p>{{ feature.text }}</p>
      </div>
    </section>

    <section class="cta-band">
      <h2>{{ t('landing.ctaBandTitle') }}</h2>
      <RouterLink :to="ctaTo" class="cta cta-alt">{{ ctaLabel }}</RouterLink>
    </section>
  </div>
</template>

<style scoped lang="scss" src="./LandingPage.scss"></style>
