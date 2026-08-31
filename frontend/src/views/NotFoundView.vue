<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<template>
  <div class="container">
    <h2>{{ t('notFound.heading') }}</h2>
    <p>
      <RouterLink to="/">{{ t('notFound.backHome') }}</RouterLink>
    </p>
  </div>
</template>
