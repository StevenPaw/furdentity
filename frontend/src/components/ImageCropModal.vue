<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { api } from '../api/client'

const props = defineProps({
  type: { type: String, required: true }, // 'avatar' | 'background'
})
const emit = defineEmits(['close', 'saved'])

const { t } = useI18n()

// Matches the ratios ProfileImageStore/the card face expect: 1:1 for the
// avatar, 9:4 for the background strip.
const ASPECT_RATIO = props.type === 'avatar' ? 1 : 9 / 4
// Comfortably above the server's own 500px cap (see ProfileImageStore) so
// the crop itself is never the limiting factor for quality.
const OUTPUT_WIDTH = 1000

// Mirrors .card-avatar in ProfileCard.scss (top: 20cqw, width: 40cqw,
// horizontally centered) – cqw there is a fraction of the *card's* width,
// which is exactly what the background crop stage's own width represents,
// so the same fractions apply directly here.
const AVATAR_SIZE_FRACTION = 0.4
const AVATAR_TOP_FRACTION = 0.2

const fileInput = ref(null)
const stage = ref(null)
const image = ref(null)

const objectUrl = ref(null)
const naturalWidth = ref(0)
const naturalHeight = ref(0)
const stageWidth = ref(0)
const stageHeight = ref(0)
const zoom = ref(1)
const offsetX = ref(0)
const offsetY = ref(0)

const saving = ref(false)
const error = ref('')

const hasImage = computed(() => objectUrl.value !== null)
// Scale at which the image exactly covers the crop stage – the floor for
// zoom, so the user can never drag the image smaller than the frame.
const baseScale = computed(() => {
  if (!naturalWidth.value || !stageWidth.value) return 1
  return Math.max(stageWidth.value / naturalWidth.value, stageHeight.value / naturalHeight.value)
})
const effectiveScale = computed(() => baseScale.value * zoom.value)
const displayWidth = computed(() => naturalWidth.value * effectiveScale.value)
const displayHeight = computed(() => naturalHeight.value * effectiveScale.value)
// `top`/`left` percentages on an absolutely positioned element resolve
// against the *container's* height/width respectively – not both against
// width like cqw does – so this needs actual pixel math rather than plain
// CSS percentages.
const avatarOverlayStyle = computed(() => {
  const size = stageWidth.value * AVATAR_SIZE_FRACTION
  return {
    width: size + 'px',
    height: size + 'px',
    top: stageWidth.value * AVATAR_TOP_FRACTION + 'px',
  }
})

function pickFile() {
  fileInput.value?.click()
}

function onFileChange(e) {
  const file = e.target.files?.[0]
  if (file) loadFile(file)
  e.target.value = ''
}

function loadFile(file) {
  error.value = ''
  if (objectUrl.value) URL.revokeObjectURL(objectUrl.value)
  objectUrl.value = URL.createObjectURL(file)
}

async function onImageLoad() {
  naturalWidth.value = image.value.naturalWidth
  naturalHeight.value = image.value.naturalHeight
  zoom.value = 1
  await nextTick()
  measureStage()
  centerImage()
}

function measureStage() {
  if (!stage.value) return
  stageWidth.value = stage.value.clientWidth
  stageHeight.value = stage.value.clientHeight
}

function centerImage() {
  offsetX.value = (stageWidth.value - displayWidth.value) / 2
  offsetY.value = (stageHeight.value - displayHeight.value) / 2
}

function clampOffset() {
  const minX = stageWidth.value - displayWidth.value
  const minY = stageHeight.value - displayHeight.value
  offsetX.value = Math.min(0, Math.max(minX, offsetX.value))
  offsetY.value = Math.min(0, Math.max(minY, offsetY.value))
}

// Re-anchors the zoom to the stage's center rather than the image's
// top-left corner: whatever content point is currently centered in the
// crop frame stays centered as the scale changes.
watch(zoom, (newZoom, oldZoom) => {
  if (!stageWidth.value) return

  const oldScale = baseScale.value * oldZoom
  const newScale = baseScale.value * newZoom
  const centerX = stageWidth.value / 2
  const centerY = stageHeight.value / 2
  const contentX = (centerX - offsetX.value) / oldScale
  const contentY = (centerY - offsetY.value) / oldScale

  offsetX.value = centerX - contentX * newScale
  offsetY.value = centerY - contentY * newScale
  clampOffset()
})

const dragState = ref(null)

function onDragStart(e) {
  if (!hasImage.value) return
  dragState.value = {
    pointerId: e.pointerId,
    startX: e.clientX,
    startY: e.clientY,
    originX: offsetX.value,
    originY: offsetY.value,
  }
  e.target.setPointerCapture?.(e.pointerId)
}

function onDragMove(e) {
  if (!dragState.value || dragState.value.pointerId !== e.pointerId) return
  offsetX.value = dragState.value.originX + (e.clientX - dragState.value.startX)
  offsetY.value = dragState.value.originY + (e.clientY - dragState.value.startY)
  clampOffset()
}

function onDragEnd() {
  dragState.value = null
}

async function save() {
  if (!hasImage.value) return

  saving.value = true
  error.value = ''
  try {
    const scale = effectiveScale.value
    const sourceX = -offsetX.value / scale
    const sourceY = -offsetY.value / scale
    const sourceW = stageWidth.value / scale
    const sourceH = stageHeight.value / scale

    const canvas = document.createElement('canvas')
    canvas.width = OUTPUT_WIDTH
    canvas.height = Math.round(OUTPUT_WIDTH / ASPECT_RATIO)
    const ctx = canvas.getContext('2d')
    ctx.drawImage(image.value, sourceX, sourceY, sourceW, sourceH, 0, 0, canvas.width, canvas.height)

    const dataUrl = canvas.toDataURL('image/jpeg', 0.9)
    const updated =
      props.type === 'avatar' ? await api.uploadAvatar(dataUrl) : await api.uploadBackground(dataUrl)
    emit('saved', updated)
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}

onBeforeUnmount(() => {
  if (objectUrl.value) URL.revokeObjectURL(objectUrl.value)
})
</script>

<template>
  <div class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal" role="dialog" aria-modal="true" :aria-label="t(`profile.crop.${type}Heading`)">
      <h3 class="modal-heading">{{ t(`profile.crop.${type}Heading`) }}</h3>

      <input
        ref="fileInput"
        type="file"
        accept="image/*"
        class="crop-file-input"
        :aria-label="t('profile.crop.chooseImage')"
        @change="onFileChange"
      />

      <template v-if="!hasImage">
        <button type="button" class="crop-pick-btn" @click="pickFile">
          {{ t('profile.crop.chooseImage') }}
        </button>
      </template>

      <template v-else>
        <div
          ref="stage"
          class="crop-stage"
          :style="{ aspectRatio: ASPECT_RATIO }"
          @pointerdown="onDragStart"
          @pointermove="onDragMove"
          @pointerup="onDragEnd"
          @pointercancel="onDragEnd"
        >
          <img
            ref="image"
            :src="objectUrl"
            alt=""
            class="crop-image"
            draggable="false"
            :style="{
              width: displayWidth + 'px',
              height: displayHeight + 'px',
              transform: `translate(${offsetX}px, ${offsetY}px)`,
            }"
            @load="onImageLoad"
          />
          <div v-if="type === 'avatar'" class="crop-circle-overlay" aria-hidden="true"></div>
          <div
            v-else-if="type === 'background'"
            class="crop-avatar-preview"
            :style="avatarOverlayStyle"
            aria-hidden="true"
          ></div>
        </div>

        <input
          v-model.number="zoom"
          type="range"
          min="1"
          max="3"
          step="0.01"
          class="crop-zoom"
          :aria-label="t('profile.crop.zoom')"
        />

        <button type="button" class="crop-pick-btn crop-pick-btn--secondary" @click="pickFile">
          {{ t('profile.crop.chooseDifferentImage') }}
        </button>
      </template>

      <p v-if="error" class="modal-error">{{ error }}</p>

      <div class="modal-actions">
        <button type="button" class="modal-cancel" @click="$emit('close')">
          {{ t('profile.cancel') }}
        </button>
        <button type="button" :disabled="!hasImage || saving" @click="save">
          {{ saving ? t('login.submitting') : t('profile.save') }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss" src="./ImageCropModal.scss"></style>
