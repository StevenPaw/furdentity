<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<template>
  <div class="container">
    <h2>{{ t('about.heading') }}</h2>
    <p>{{ t('about.text') }}</p>
  </div>
</template>
