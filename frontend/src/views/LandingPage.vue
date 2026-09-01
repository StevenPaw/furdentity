<script setup>
import { useI18n } from 'vue-i18n'
import logo from '../assets/Furdentity-Logo-White.svg'
import ProfileCarousel from '../components/ProfileCarousel.vue'

const { t, tm } = useI18n()
</script>

<template>
  <div class="landing">
    <section class="hero">
      <img :src="logo" :alt="t('app.title')" class="hero-logo" />
      <h1>{{ t('landing.heroTitle') }}</h1>
      <p class="hero-subtitle">{{ t('landing.heroSubtitle') }}</p>
      <RouterLink to="/register" class="cta">{{ t('landing.cta') }}</RouterLink>
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
      <RouterLink to="/register" class="cta cta-alt">{{ t('landing.cta') }}</RouterLink>
    </section>
  </div>
</template>

<style scoped lang="scss" src="./LandingPage.scss"></style>
