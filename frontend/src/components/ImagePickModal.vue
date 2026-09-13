<script setup>
import { useI18n } from 'vue-i18n'

defineProps({
  type: { type: String, required: true }, // 'avatar' | 'background'
})
const emit = defineEmits(['close', 'picked'])

const { t } = useI18n()

function onFileChange(e) {
  const file = e.target.files?.[0]
  if (file) emit('picked', file)
  e.target.value = ''
}
</script>

<template>
  <div class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal" role="dialog" aria-modal="true" :aria-label="t(`profile.crop.${type}Heading`)">
      <h3 class="modal-heading">{{ t(`profile.crop.${type}Heading`) }}</h3>

      <label class="crop-pick-btn">
        {{ t('profile.crop.chooseImage') }}
        <input
          type="file"
          accept="image/*"
          class="crop-file-input"
          :aria-label="t('profile.crop.chooseImage')"
          @change="onFileChange"
        />
      </label>

      <div class="modal-actions">
        <button type="button" class="modal-cancel" @click="$emit('close')">
          {{ t('profile.cancel') }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss" src="./ImagePickModal.scss"></style>
