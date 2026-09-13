<script setup>
import { useI18n } from 'vue-i18n'
import { AVATAR_SHAPES, AVATAR_SHAPE_LABEL_KEYS } from '../utils/avatarShapes'

defineProps({
  type: { type: String, required: true }, // 'avatar' | 'background'
  zoom: { type: Number, required: true },
  shape: { type: String, default: 'circle' },
  saving: { type: Boolean, default: false },
  error: { type: String, default: '' },
})
const emit = defineEmits(['update:zoom', 'update:shape', 'pickDifferent', 'cancel', 'confirm'])

const { t } = useI18n()

function onFileChange(e) {
  const file = e.target.files?.[0]
  if (file) emit('pickDifferent', file)
  e.target.value = ''
}
</script>

<template>
  <div class="card-adjust-panel" :class="`card-adjust-panel--${type}`">
    <label class="card-adjust-zoom-row">
      <span>{{ t('profile.crop.zoom') }}</span>
      <input
        type="range"
        min="1"
        max="3"
        step="0.01"
        :value="zoom"
        :aria-label="t('profile.crop.zoom')"
        @input="$emit('update:zoom', Number($event.target.value))"
      />
    </label>

    <div
      v-if="type === 'avatar'"
      class="shape-picker"
      role="radiogroup"
      :aria-label="t('profile.designAvatarShape')"
    >
      <button
        v-for="option in AVATAR_SHAPES"
        :key="option.key"
        type="button"
        class="shape-option"
        :class="{ 'shape-option--active': shape === option.key }"
        role="radio"
        :aria-checked="shape === option.key"
        :aria-label="t(AVATAR_SHAPE_LABEL_KEYS[option.key])"
        :title="t(AVATAR_SHAPE_LABEL_KEYS[option.key])"
        @click="$emit('update:shape', option.key)"
      >
        <span
          class="shape-option-swatch"
          :style="{ borderRadius: option.borderRadius, clipPath: option.clipPath }"
        ></span>
      </button>
    </div>

    <label class="card-adjust-pick-btn">
      {{ t('profile.crop.chooseDifferentImage') }}
      <input
        type="file"
        accept="image/*"
        class="card-adjust-file-input"
        :aria-label="t('profile.crop.chooseDifferentImage')"
        @change="onFileChange"
      />
    </label>

    <p v-if="error" class="card-adjust-error">{{ error }}</p>

    <div class="card-adjust-actions">
      <button
        type="button"
        class="card-adjust-btn card-adjust-btn--cancel"
        :disabled="saving"
        :aria-label="t('profile.cancel')"
        :title="t('profile.cancel')"
        @click="$emit('cancel')"
      >
        ✕
      </button>
      <button
        type="button"
        class="card-adjust-btn card-adjust-btn--confirm"
        :disabled="saving"
        :aria-label="t('profile.save')"
        :title="t('profile.save')"
        @click="$emit('confirm')"
      >
        ✓
      </button>
    </div>
  </div>
</template>

<style scoped lang="scss" src="./CardImageAdjustPanel.scss"></style>
