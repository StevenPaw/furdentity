<script setup>
import { useI18n } from 'vue-i18n'
import FlagBadge from './FlagBadge.vue'

defineProps({
  flagKey: { type: String, default: null },
  editMode: { type: Boolean, default: false },
  size: { type: String, default: 'big' }, // 'big' | 'small'
  editLabel: { type: String, required: true },
  dim: { type: Boolean, default: false },
})
defineEmits(['pick'])

const { t } = useI18n()
</script>

<template>
  <div class="flag-slot" :class="[`flag-slot--${size}`, { 'card-focus-dim': dim }]">
    <FlagBadge v-if="flagKey" :flag-key="flagKey" />
    <button
      v-else-if="editMode"
      type="button"
      class="flag-slot-empty"
      :aria-label="t('profile.addFlag')"
      :title="t('profile.addFlag')"
      @click="$emit('pick')"
    >
      +
    </button>
    <button
      v-if="editMode && flagKey"
      type="button"
      class="flag-slot-edit"
      :aria-label="editLabel"
      :title="editLabel"
      @click="$emit('pick')"
    >
      ✎
    </button>
  </div>
</template>

<style scoped lang="scss" src="./FlagSlot.scss"></style>
