<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { api } from '../api/client'
import { avatarShapeStyle, DEFAULT_AVATAR_SHAPE } from '../utils/avatarShapes'

// How many profiles we ask the backend for – matches
// PublicApiController::RANDOM_PROFILES_DEFAULT_LIMIT, just kept explicit
// here since this is also what drives the "how weird does a short loop
// look" judgement below.
const REQUESTED_COUNT = 16
// Below this many distinct profiles, a seamlessly-looping duplicated strip
// would show the same couple of cards passing by too often to feel like a
// real "browse the community" slider – simpler to just render them once,
// centered, un-animated, until there are enough to make looping worthwhile.
const MIN_FOR_LOOP = 6

const { t } = useI18n()

const profiles = ref([])
const loaded = ref(false)

// Duplicated once so the CSS animation can scroll exactly one full copy's
// width and land back on an identical frame – seamless looping without
// tracking scroll position in JS.
const loopProfiles = computed(() => [...profiles.value, ...profiles.value])
const shouldLoop = computed(() => profiles.value.length >= MIN_FOR_LOOP)
// Keeps per-card speed roughly constant instead of the whole strip taking
// longer just because there happen to be more cards.
const animationDuration = computed(() => `${profiles.value.length * 4}s`)

function cardStyle(profile) {
  const shape = avatarShapeStyle(profile.avatarShape || DEFAULT_AVATAR_SHAPE)
  return {
    '--mini-card-maincolor': profile.mainColor || '#6c5ce7',
    '--mini-card-secondarycolor': profile.secondaryColor || profile.mainColor || '#6c5ce7',
    '--mini-card-avatar-radius': shape.borderRadius,
    '--mini-card-avatar-clip-path': shape.clipPath,
  }
}

onMounted(async () => {
  try {
    profiles.value = (await api.randomProfiles(REQUESTED_COUNT)).data
  } catch {
    profiles.value = []
  } finally {
    loaded.value = true
  }
})
</script>

<template>
  <section v-if="loaded && profiles.length" class="profile-carousel">
    <h2>{{ t('landing.carouselTitle') }}</h2>

    <div class="carousel-track" :class="{ 'carousel-track--loop': shouldLoop }">
      <div
        class="carousel-strip"
        :class="{ 'carousel-strip--loop': shouldLoop }"
        :style="{ animationDuration }"
      >
        <RouterLink
          v-for="(profile, i) in shouldLoop ? loopProfiles : profiles"
          :key="`${profile.id}-${i}`"
          :to="`/id/${profile.handle}`"
          class="mini-card"
          :style="cardStyle(profile)"
          :aria-hidden="shouldLoop && i >= profiles.length"
          :tabindex="shouldLoop && i >= profiles.length ? -1 : 0"
        >
          <div
            class="mini-card-background"
            :style="profile.backgroundUrl ? { backgroundImage: `url(${profile.backgroundUrl})` } : null"
          ></div>
          <div
            class="mini-card-avatar"
            :style="profile.avatarUrl ? { backgroundImage: `url(${profile.avatarUrl})` } : null"
          ></div>
          <p class="mini-card-title">{{ profile.title }}</p>
        </RouterLink>
      </div>
    </div>
  </section>
</template>

<style scoped lang="scss" src="./ProfileCarousel.scss"></style>
