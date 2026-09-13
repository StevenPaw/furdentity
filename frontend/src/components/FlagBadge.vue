<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { getFlag } from '../utils/flags'

const props = defineProps({
  flagKey: { type: String, default: null },
})

const { locale } = useI18n()
const flag = computed(() => getFlag(props.flagKey, locale.value))
</script>

<template>
  <div class="flag-badge" :title="flag?.label">
    <span
      v-if="flag?.type === 'country'"
      class="flag-badge_icon fi"
      :class="flag.iconClass"
    ></span>
    <span
      v-else-if="flag?.type === 'pride'"
      class="flag-badge_icon flag"
      :class="flag.iconClass"
    ></span>
  </div>
</template>

<style scoped lang="scss">
.flag-badge {
  width: 100%;
  height: 100%;
  border-radius: 3px;
  overflow: hidden;
  background: rgba(255, 255, 255, 0.12);
}

// Overrides both libraries' own fixed em/px sizing (see flag-icons.css's
// .fi and pride-flags.css's .flag) so the badge fills exactly the box
// .flag-slot in ProfileCard.scss (or FlagPickerModal.scss's .flag-option)
// gives it, at whatever size that is. Resetting aspect-ratio/min-width too
// – pride-flags' .flag sets aspect-ratio: 14/9, which past just width/height
// alone still fed into the picker grid's min-content sizing (see
// .flag-option's min-width: 0 in FlagPickerModal.scss for the other half of
// that fix).
.flag-badge_icon {
  display: block;
  width: 100%;
  height: 100%;
  min-width: 0;
  aspect-ratio: auto;
  // Both libraries set their own background-size: contain (see flag-icons'
  // .fi / pride-flags' .flag) - fine only as long as the badge box happens
  // to share the flag's native ratio exactly, which isn't guaranteed once
  // every flag slot (see ProfileCard.scss's .flag-slot) can be one of
  // several fixed sizes. `cover` (the background-image equivalent of
  // object-fit: cover) crops instead of letterboxing when they don't match,
  // same intent as object-fit: cover on an <img>. !important because both
  // libraries' own rules match with the same specificity as this one, so
  // the actual winner would otherwise depend on unpredictable stylesheet
  // injection order between this scoped style and the global CSS imports.
  background-size: cover !important;
}
</style>
