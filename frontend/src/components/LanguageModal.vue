<script setup>
import { useI18n } from 'vue-i18n'
import { SUPPORTED_LOCALES, setLocale } from '../i18n'

const emit = defineEmits(['close'])

const { t, locale } = useI18n()

const LOCALE_LABELS = {
  en: 'English',
  de: 'Deutsch',
}

function choose(l) {
  setLocale(l)
  emit('close')
}
</script>

<template>
  <div class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal" role="dialog" aria-modal="true" :aria-label="t('nav.language')">
      <div class="modal-header">
        <h3 class="modal-heading">{{ t('nav.language') }}</h3>
        <button
          type="button"
          class="modal-close"
          :aria-label="t('profile.close')"
          :title="t('profile.close')"
          @click="$emit('close')"
        >
          ✕
        </button>
      </div>

      <ul class="locale-list">
        <li v-for="l in SUPPORTED_LOCALES" :key="l">
          <button
            type="button"
            class="locale-option"
            :class="{ 'locale-option--active': l === locale }"
            :aria-pressed="l === locale"
            @click="choose(l)"
          >
            {{ LOCALE_LABELS[l] || l.toUpperCase() }}
          </button>
        </li>
      </ul>
    </div>
  </div>
</template>

<style scoped lang="scss" src="./LanguageModal.scss"></style>
