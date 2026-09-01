<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { api } from '../api/client'
import { SPECIAL_FLAGS, getCountryFlags } from '../utils/flags'
import FlagBadge from './FlagBadge.vue'

const props = defineProps({
  side: { type: String, required: true }, // 'left' | 'right'
  currentKey: { type: String, default: null },
})
const emit = defineEmits(['close', 'saved'])

const { t, locale } = useI18n()

const search = ref('')
const saving = ref(false)
const error = ref('')

const query = computed(() => search.value.trim().toLowerCase())
const filteredSpecial = computed(() =>
  SPECIAL_FLAGS.filter((f) => f.label.toLowerCase().includes(query.value))
)
const filteredCountries = computed(() => {
  const all = getCountryFlags(locale.value)
  return query.value ? all.filter((f) => f.label.toLowerCase().includes(query.value)) : all
})

async function select(key) {
  saving.value = true
  error.value = ''
  try {
    const field = props.side === 'left' ? 'flagLeft' : 'flagRight'
    const updated = await api.updateMe({ [field]: key })
    emit('saved', updated)
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal flag-modal" role="dialog" aria-modal="true" :aria-label="t('profile.flagModalTitle')">
      <h3 class="modal-heading">{{ t('profile.flagModalTitle') }}</h3>

      <input
        v-model="search"
        type="text"
        class="flag-search"
        :placeholder="t('profile.flagSearchPlaceholder')"
        autofocus
      />

      <div class="flag-grid-scroll">
        <template v-if="filteredSpecial.length">
          <p class="flag-group-heading">{{ t('profile.flagGroupSpecial') }}</p>
          <div class="flag-grid">
            <button
              v-for="f in filteredSpecial"
              :key="f.key"
              type="button"
              class="flag-option"
              :class="{ 'flag-option--active': f.key === currentKey }"
              :title="f.label"
              :disabled="saving"
              @click="select(f.key)"
            >
              <FlagBadge :flag-key="f.key" />
            </button>
          </div>
        </template>

        <template v-if="filteredCountries.length">
          <p class="flag-group-heading">{{ t('profile.flagGroupCountries') }}</p>
          <div class="flag-grid">
            <button
              v-for="f in filteredCountries"
              :key="f.key"
              type="button"
              class="flag-option"
              :class="{ 'flag-option--active': f.key === currentKey }"
              :title="f.label"
              :disabled="saving"
              @click="select(f.key)"
            >
              <FlagBadge :flag-key="f.key" />
            </button>
          </div>
        </template>

        <p v-if="!filteredSpecial.length && !filteredCountries.length" class="flag-no-results">
          {{ t('profile.flagNoResults') }}
        </p>
      </div>

      <p v-if="error" class="modal-error">{{ error }}</p>

      <div class="modal-actions">
        <button v-if="currentKey" type="button" class="modal-delete" :disabled="saving" @click="select('')">
          {{ t('profile.flagRemove') }}
        </button>
        <span class="modal-actions_spacer"></span>
        <button type="button" class="modal-cancel" @click="$emit('close')">
          {{ t('profile.cancel') }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss" src="./FlagPickerModal.scss"></style>
