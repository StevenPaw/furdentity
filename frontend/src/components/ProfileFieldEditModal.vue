<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { api } from '../api/client'

const props = defineProps({
  field: { type: String, required: true }, // 'title' | 'species' | 'bio'
  modelValue: { type: String, default: '' },
})
const emit = defineEmits(['close', 'saved'])

const { t } = useI18n()

// maxLength keeps the card-face title to what actually fits in 2 lines
// there (see .card-title's line-clamp in ProfileCard.scss).
const FIELD_CONFIG = {
  title: { labelKey: 'register.title', multiline: false, required: true, maxLength: 30 },
  species: { labelKey: 'home.species', multiline: false, required: false },
  bio: { labelKey: 'home.bio', multiline: true, required: false },
}
const config = FIELD_CONFIG[props.field]

const value = ref(props.modelValue)
const saving = ref(false)
const error = ref('')

async function save() {
  if (config.required && !value.value.trim()) {
    error.value = t('profile.fieldRequired')
    return
  }

  saving.value = true
  error.value = ''
  try {
    const updated = await api.updateMe({ [props.field]: value.value })
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
    <div class="modal" role="dialog" aria-modal="true" :aria-label="t(config.labelKey)">
      <label :for="'field-' + field">{{ t(config.labelKey) }}</label>
      <textarea
        v-if="config.multiline"
        :id="'field-' + field"
        v-model="value"
        rows="4"
        autofocus
      ></textarea>
      <input
        v-else
        :id="'field-' + field"
        v-model="value"
        type="text"
        autofocus
        :maxlength="config.maxLength"
      />
      <p v-if="config.maxLength" class="modal-char-count">
        {{ value.length }}/{{ config.maxLength }} {{ t('profile.characters') }}
      </p>

      <p v-if="error" class="modal-error">{{ error }}</p>

      <div class="modal-actions">
        <button type="button" class="modal-cancel" @click="$emit('close')">
          {{ t('profile.cancel') }}
        </button>
        <button type="button" :disabled="saving" @click="save">
          {{ saving ? t('login.submitting') : t('profile.save') }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss" src="./ProfileFieldEditModal.scss"></style>
