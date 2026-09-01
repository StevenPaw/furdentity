<script setup>
import { ref, computed, watchEffect } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { api, isAuthenticated } from '../api/client'
import { getPlatform } from '../utils/socialPlatforms'
import ProfileFieldEditModal from '../components/ProfileFieldEditModal.vue'
import LinkEditModal from '../components/LinkEditModal.vue'
import ImageCropModal from '../components/ImageCropModal.vue'
import FlagPickerModal from '../components/FlagPickerModal.vue'
import FlagBadge from '../components/FlagBadge.vue'
import DesignPickerModal from '../components/DesignPickerModal.vue'
import designIcon from '../assets/icons/design.svg'

const route = useRoute()
const { t } = useI18n()

const profile = ref(null)
const notFound = ref(false)
const ownHandle = ref(null)

const editMode = ref(false)
const activeField = ref(null) // 'title' | 'species' | 'bio' | null
const linkModalTarget = ref(null) // null (closed) | 'new' | a link object – for the below-card list
const cardLinkModalTarget = ref(null) // same, but for the 3 on-card slots
const draggedLinkIndex = ref(null)
const imageCropTarget = ref(null) // null (closed) | 'avatar' | 'background'
const flagPickerSide = ref(null) // null (closed) | 'left' | 'right'
const designModalOpen = ref(false)

const isOwner = computed(() => ownHandle.value !== null && profile.value?.handle === ownHandle.value)
// The unlimited list shown below the card.
const belowLinks = computed(() => (profile.value?.links || []).filter((l) => l.placement !== 'card'))
// Up to 3 links placed directly on the card face, managed completely
// independently of the list above – always exactly 3 slots (null = empty).
const cardLinkSlots = computed(() => {
  const filled = (profile.value?.links || []).filter((l) => l.placement === 'card')
  return [filled[0] ?? null, filled[1] ?? null, filled[2] ?? null]
})

watchEffect(async () => {
  const handle = route.params.handle
  profile.value = null
  notFound.value = false
  editMode.value = false
  activeField.value = null
  linkModalTarget.value = null
  cardLinkModalTarget.value = null
  imageCropTarget.value = null
  flagPickerSide.value = null
  designModalOpen.value = false

  try {
    profile.value = await api.profileByHandle(handle)
  } catch {
    notFound.value = true
    return
  }

  if (isAuthenticated()) {
    try {
      const me = await api.me()
      ownHandle.value = me.handle
    } catch {
      ownHandle.value = null
    }
  } else {
    ownHandle.value = null
  }
})

function onFieldSaved(updated) {
  profile.value = { ...profile.value, ...updated }
  activeField.value = null
}

function onImageSaved(updated) {
  profile.value = { ...profile.value, ...updated }
  imageCropTarget.value = null
}

function onFlagSaved(updated) {
  profile.value = { ...profile.value, ...updated }
  flagPickerSide.value = null
}

function onDesignSaved(updated) {
  profile.value = { ...profile.value, ...updated }
  designModalOpen.value = false
}

function onLinkSaved(link) {
  const links = [...profile.value.links]
  const index = links.findIndex((l) => l.id === link.id)
  if (index === -1) links.push(link)
  else links.splice(index, 1, link)
  links.sort((a, b) => a.sortOrder - b.sortOrder)
  profile.value = { ...profile.value, links }
  linkModalTarget.value = null
  cardLinkModalTarget.value = null
}

function onLinkDeleted(id) {
  profile.value = { ...profile.value, links: profile.value.links.filter((l) => l.id !== id) }
  linkModalTarget.value = null
  cardLinkModalTarget.value = null
}

function onLinkDragStart(index) {
  draggedLinkIndex.value = index
}

async function onLinkDrop(targetIndex) {
  const from = draggedLinkIndex.value
  draggedLinkIndex.value = null
  if (from === null || from === targetIndex) return

  const reordered = [...belowLinks.value]
  const [moved] = reordered.splice(from, 1)
  reordered.splice(targetIndex, 0, moved)

  const cardLinks = profile.value.links.filter((l) => l.placement === 'card')
  profile.value = { ...profile.value, links: [...reordered, ...cardLinks] }

  try {
    const result = await api.reorderLinks(reordered.map((l) => l.id))
    profile.value = { ...profile.value, links: result.data }
  } catch {
    // Local order still reflects the intended change; it'll resync on next load.
  }
}
</script>

<template>
  <div
    v-if="profile?.backgroundUrl"
    class="page-backdrop"
    :style="{ backgroundImage: `url(${profile.backgroundUrl})` }"
    aria-hidden="true"
  ></div>
  <div
    class="container"
    :style="{
      '--card-maincolor': profile?.mainColor || '#6c5ce7',
      '--card-secondarycolor': profile?.secondaryColor || profile?.mainColor || '#6c5ce7',
    }"
  >
    <template v-if="notFound">
      <p>{{ t('profile.notFound') }}</p>
    </template>
    <template v-else-if="profile">
      <p v-if="isOwner" class="edit-bar">
        <button
          v-if="editMode"
          type="button"
          class="design-toggle-btn"
          :aria-label="t('profile.editDesign')"
          :title="t('profile.editDesign')"
          @click="designModalOpen = true"
        >
          <img :src="designIcon" alt="" />
        </button>
        <span class="edit-bar_spacer"></span>
        <button type="button" class="edit-toggle" @click="editMode = !editMode">
          {{ editMode ? t('profile.done') : t('profile.edit') }}
        </button>
      </p>

      <div class="card">
        <div
          class="card-background"
          :style="profile.backgroundUrl ? { backgroundImage: `url(${profile.backgroundUrl})` } : null"
        >
          <button
            v-if="editMode"
            type="button"
            class="card-image-edit-btn card-image-edit-btn--background"
            :aria-label="t('profile.editBackground')"
            :title="t('profile.editBackground')"
            @click="imageCropTarget = 'background'"
          >
            ✎
          </button>
        </div>
        <div
          class="card-avatar"
          :style="profile.avatarUrl ? { backgroundImage: `url(${profile.avatarUrl})` } : null"
        >
          <button
            v-if="editMode"
            type="button"
            class="card-image-edit-btn card-image-edit-btn--avatar"
            :aria-label="t('profile.editAvatar')"
            :title="t('profile.editAvatar')"
            @click="imageCropTarget = 'avatar'"
          >
            ✎
          </button>
        </div>
        <div v-if="profile.flagLeft || editMode" class="flag-slot flag-slot--left">
          <FlagBadge v-if="profile.flagLeft" :flag-key="profile.flagLeft" />
          <button
            v-else-if="editMode"
            type="button"
            class="flag-slot-empty"
            :aria-label="t('profile.addFlag')"
            :title="t('profile.addFlag')"
            @click="flagPickerSide = 'left'"
          >
            +
          </button>
          <button
            v-if="editMode && profile.flagLeft"
            type="button"
            class="flag-slot-edit"
            :aria-label="t('profile.editFlagLeft')"
            :title="t('profile.editFlagLeft')"
            @click="flagPickerSide = 'left'"
          >
            ✎
          </button>
        </div>

        <div v-if="profile.flagRight || editMode" class="flag-slot flag-slot--right">
          <FlagBadge v-if="profile.flagRight" :flag-key="profile.flagRight" />
          <button
            v-else-if="editMode"
            type="button"
            class="flag-slot-empty"
            :aria-label="t('profile.addFlag')"
            :title="t('profile.addFlag')"
            @click="flagPickerSide = 'right'"
          >
            +
          </button>
          <button
            v-if="editMode && profile.flagRight"
            type="button"
            class="flag-slot-edit"
            :aria-label="t('profile.editFlagRight')"
            :title="t('profile.editFlagRight')"
            @click="flagPickerSide = 'right'"
          >
            ✎
          </button>
        </div>

        <div class="card-body">
          <div class="card-row card-row--title">
            <h1 class="card-title">{{ profile.title }}</h1>
            <button
              v-if="editMode"
              type="button"
              class="card-field-edit-btn"
              :aria-label="t('register.title')"
              @click="activeField = 'title'"
            >
              ✎
            </button>
          </div>

          <div v-if="editMode || profile.species" class="card-row card-row--species">
            <p class="card-species" :class="{ 'card-species--empty': !profile.species }">
              {{ profile.species || t('profile.addSpecies') }}
            </p>
            <button
              v-if="editMode"
              type="button"
              class="card-field-edit-btn"
              :aria-label="t('home.species')"
              @click="activeField = 'species'"
            >
              ✎
            </button>
          </div>

          <hr class="card-divider" />

          <div v-if="editMode || profile.bio" class="card-row card-row--bio">
            <p class="card-bio" :class="{ 'card-bio--empty': !profile.bio }">
              {{ profile.bio || t('profile.addBio') }}
            </p>
            <button
              v-if="editMode"
              type="button"
              class="card-field-edit-btn"
              :aria-label="t('home.bio')"
              @click="activeField = 'bio'"
            >
              ✎
            </button>
          </div>

          <div v-if="editMode || cardLinkSlots.some(Boolean)" class="card-links">
            <div v-for="(slot, i) in cardLinkSlots" v-show="editMode || slot" :key="i" class="card-link-slot">
              <template v-if="slot">
                <a
                  :href="slot.url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="card-link-icon"
                  :aria-label="slot.title || getPlatform(slot.platform).label"
                  :title="slot.title || getPlatform(slot.platform).label"
                >
                  <img :src="getPlatform(slot.platform).icon" alt="" />
                </a>
                <button
                  v-if="editMode"
                  type="button"
                  class="card-link-slot-edit"
                  :aria-label="t('profile.edit')"
                  :title="t('profile.edit')"
                  @click="cardLinkModalTarget = slot"
                >
                  ✎
                </button>
              </template>
              <button
                v-else-if="editMode"
                type="button"
                class="card-link-icon card-link-icon--empty"
                :aria-label="t('profile.addLink')"
                :title="t('profile.addLink')"
                @click="cardLinkModalTarget = 'new'"
              >
                +
              </button>
            </div>
          </div>
        </div>
      </div>

      <div v-if="editMode || belowLinks.length" class="links-section">
        <ul class="links-list">
          <li
            v-for="(link, index) in belowLinks"
            :key="link.id"
            class="link-item"
            :draggable="editMode"
            @dragstart="onLinkDragStart(index)"
            @dragover.prevent
            @drop="onLinkDrop(index)"
          >
            <span v-if="editMode" class="link-drag-handle" aria-hidden="true">⠿</span>
            <a :href="link.url" target="_blank" rel="noopener noreferrer" class="link-row">
              <img :src="getPlatform(link.platform).icon" alt="" class="link-icon" />
              <span class="link-title">{{ link.title || link.url }}</span>
            </a>
            <button
              v-if="editMode"
              type="button"
              class="card-field-edit-btn"
              :aria-label="t('profile.edit')"
              @click="linkModalTarget = link"
            >
              ✎
            </button>
          </li>
        </ul>

        <button
          v-if="editMode"
          type="button"
          class="link-add-btn"
          @click="linkModalTarget = 'new'"
        >
          + {{ t('profile.addLink') }}
        </button>
      </div>

      <ProfileFieldEditModal
        v-if="activeField"
        :field="activeField"
        :model-value="profile[activeField] || ''"
        @close="activeField = null"
        @saved="onFieldSaved"
      />

      <LinkEditModal
        v-if="linkModalTarget"
        :link="linkModalTarget === 'new' ? null : linkModalTarget"
        placement="below"
        @close="linkModalTarget = null"
        @saved="onLinkSaved"
        @deleted="onLinkDeleted"
      />

      <LinkEditModal
        v-if="cardLinkModalTarget"
        :link="cardLinkModalTarget === 'new' ? null : cardLinkModalTarget"
        placement="card"
        @close="cardLinkModalTarget = null"
        @saved="onLinkSaved"
        @deleted="onLinkDeleted"
      />

      <ImageCropModal
        v-if="imageCropTarget"
        :type="imageCropTarget"
        @close="imageCropTarget = null"
        @saved="onImageSaved"
      />

      <FlagPickerModal
        v-if="flagPickerSide"
        :side="flagPickerSide"
        :current-key="flagPickerSide === 'left' ? profile.flagLeft : profile.flagRight"
        @close="flagPickerSide = null"
        @saved="onFlagSaved"
      />

      <DesignPickerModal
        v-if="designModalOpen"
        :main-color="profile.mainColor"
        :secondary-color="profile.secondaryColor"
        @close="designModalOpen = false"
        @saved="onDesignSaved"
      />
    </template>
  </div>
</template>

<style scoped lang="scss" src="./ProfileView.scss"></style>
