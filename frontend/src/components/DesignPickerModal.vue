<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { api } from '../api/client'
import { AVATAR_SHAPES, AVATAR_SHAPE_LABEL_KEYS, DEFAULT_AVATAR_SHAPE } from '../utils/avatarShapes'

const DEFAULT_MAIN_COLOR = '#6c5ce7'
const DEFAULT_SECONDARY_COLOR = '#5ac85a'

const props = defineProps({
  mainColor: { type: String, default: null },
  secondaryColor: { type: String, default: null },
  avatarShape: { type: String, default: null },
})
const emit = defineEmits(['close', 'saved'])

const { t } = useI18n()

const gradient = ref(!!props.secondaryColor)
const main = ref(props.mainColor || DEFAULT_MAIN_COLOR)
const secondary = ref(props.secondaryColor || DEFAULT_SECONDARY_COLOR)
const shape = ref(props.avatarShape || DEFAULT_AVATAR_SHAPE)
const saving = ref(false)
const error = ref('')

async function save() {
  saving.value = true
  error.value = ''
  try {
    const updated = await api.updateMe({
      mainColor: main.value,
      // Empty string clears the slot – switches the card to solid-color mode.
      secondaryColor: gradient.value ? secondary.value : '',
      avatarShape: shape.value,
    })
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
    <div class="modal design-modal" role="dialog" aria-modal="true" :aria-label="t('profile.designModalTitle')">
      <h3 class="modal-heading">{{ t('profile.designModalTitle') }}</h3>

      <label class="design-field" for="design-main-color">
        {{ t('profile.designMainColor') }}
        <input id="design-main-color" v-model="main" type="color" />
      </label>

      <label class="design-toggle">
        <input v-model="gradient" type="checkbox" />
        {{ t('profile.designUseGradient') }}
      </label>

      <label v-if="gradient" class="design-field" for="design-secondary-color">
        {{ t('profile.designSecondaryColor') }}
        <input id="design-secondary-color" v-model="secondary" type="color" />
      </label>

      <p class="design-field-label">{{ t('profile.designAvatarShape') }}</p>
      <div class="shape-picker" role="radiogroup" :aria-label="t('profile.designAvatarShape')">
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
          @click="shape = option.key"
        >
          <span
            class="shape-option-swatch"
            :style="{ borderRadius: option.borderRadius, clipPath: option.clipPath }"
          ></span>
        </button>
      </div>

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

<style scoped lang="scss" src="./DesignPickerModal.scss"></style>
