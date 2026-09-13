<script setup>
import { ref, computed, watch, watchEffect } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { api, isAuthenticated } from '../api/client'
import { getPlatform } from '../utils/socialPlatforms'
import ProfileFieldEditModal from '../components/ProfileFieldEditModal.vue'
import LinkEditModal from '../components/LinkEditModal.vue'
import ImagePickModal from '../components/ImagePickModal.vue'
import CardImageAdjustPanel from '../components/CardImageAdjustPanel.vue'
import FlagPickerModal from '../components/FlagPickerModal.vue'
import FlagSlot from '../components/FlagSlot.vue'
import DesignPickerModal from '../components/DesignPickerModal.vue'
import designIcon from '../assets/icons/design.svg'
import { avatarShapeMaskImage, DEFAULT_AVATAR_SHAPE } from '../utils/avatarShapes'
import { useImageAdjuster } from '../composables/useImageAdjuster'

// Comfortably above the server's own 500px cap (see ProfileImageStore) so
// the crop itself is never the limiting factor for quality.
const OUTPUT_WIDTH = 1000

// Mirrors .card-background's own CSS gradient (ProfileCard.scss) - repeated
// here (rather than only in the stylesheet) so a saved background photo can
// be layered *on top* of it via a single inline background-image, letting
// the same gradient show through any transparent part of that photo instead
// of the plain white/black a browser would otherwise composite against.
const CARD_BACKGROUND_GRADIENT_CSS =
  'linear-gradient(to bottom right, var(--card-maincolor, #6c5ce7), var(--card-secondarycolor, #6c5ce7))'

const route = useRoute()
const { t } = useI18n()

const profile = ref(null)
const notFound = ref(false)
const isPrivate = ref(false)
const ownHandle = ref(null)

const editMode = ref(false)
const activeField = ref(null) // 'title' | 'species' | 'bio' | null
const linkModalTarget = ref(null) // null (closed) | 'new' | a link object – for the below-card list
const cardLinkModalTarget = ref(null) // same, but for the 3 on-card slots
const draggedLinkIndex = ref(null)
const flagPickerField = ref(null) // null (closed) | 'flagLeftInner' | 'flagLeftOuter' | 'flagRightInner' | 'flagRightOuter'
const designModalOpen = ref(false)

// Image editing: a minimal picker modal ('pickModalTarget') first collects
// the file, then the live adjustment happens directly on the real card face
// ('adjustTarget') instead of in a standalone crop-stage modal - see
// CardImageAdjustPanel.vue and composables/useImageAdjuster.js.
const pickModalTarget = ref(null) // null (closed) | 'avatar' | 'background'
const adjustTarget = ref(null) // null (inactive) | 'avatar' | 'background'
const adjustObjectUrl = ref(null)
const adjustShape = ref(DEFAULT_AVATAR_SHAPE)
const adjustSaving = ref(false)
const adjustError = ref('')

// Revokes the previous object URL whenever a new one is picked (or the
// session is cancelled) - the one, single place this happens, so nothing
// else needs to read adjustObjectUrl.value just to decide whether to revoke
// it. That matters beyond tidiness: the profile route watchEffect below
// resets adjustTarget/adjustObjectUrl on every route change, and if that
// reset read adjustObjectUrl.value itself (e.g. via an `if (...)` guard), it
// would make the *whole* watchEffect reactively depend on it - silently
// re-running (and wiping profile/editMode) every time an in-progress image
// adjustment picks a new file, with no navigation involved at all.
watch(adjustObjectUrl, (url, previousUrl) => {
  if (previousUrl) URL.revokeObjectURL(previousUrl)
})

const {
  zoom: adjustZoom,
  offsetX: adjustOffsetX,
  offsetY: adjustOffsetY,
  displayWidth: adjustDisplayWidth,
  displayHeight: adjustDisplayHeight,
  setStage: setAdjustStage,
  setImage: setAdjustImageEl,
  onImageLoad: onAdjustImageLoad,
  onDragStart: onAdjustDragStart,
  onDragMove: onAdjustDragMove,
  onDragEnd: onAdjustDragEnd,
  exportDataUrl: exportAdjustDataUrl,
} = useImageAdjuster()

const isOwner = computed(() => ownHandle.value !== null && profile.value?.handle === ownHandle.value)
// While live-adjusting the avatar, the shape picked in the panel should be
// reflected on the card face immediately (border-radius/clip-path), not
// only after saving - falls back to the profile's saved shape otherwise.
const activeAvatarShape = computed(() =>
  adjustTarget.value === 'avatar' ? adjustShape.value : profile.value?.avatarShape || DEFAULT_AVATAR_SHAPE,
)
// The outer flag slot is only shown smaller once there's an inner flag next
// to it to be secondary to - as the only flag set on that side, it takes
// the same full size the inner slot would, so a single flag per side still
// looks exactly like it did before there were two slots.
const leftOuterIsBig = computed(() => !profile.value?.flagLeftInner)
const rightOuterIsBig = computed(() => !profile.value?.flagRightInner)
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
  isPrivate.value = false
  editMode.value = false
  activeField.value = null
  linkModalTarget.value = null
  cardLinkModalTarget.value = null
  pickModalTarget.value = null
  cancelAdjust()
  flagPickerField.value = null
  designModalOpen.value = false

  // Resolved first (rather than after the profile fetch) because a hidden
  // profile needs to know right away whether the visitor *is* its owner –
  // that's the one case allowed to see it anyway (see below).
  if (isAuthenticated()) {
    try {
      ownHandle.value = (await api.me()).handle
    } catch {
      ownHandle.value = null
    }
  } else {
    ownHandle.value = null
  }

  if (ownHandle.value === handle) {
    // toOwnApiData() is a superset of the public shape (adds email, which
    // this view never renders), so it's safe to use directly here too –
    // and it's the only way for the owner to see their own
    // User::VISIBILITY_HIDDEN profile at all.
    try {
      profile.value = await api.me()
    } catch {
      notFound.value = true
    }
    return
  }

  try {
    profile.value = await api.profileByHandle(handle)
  } catch (e) {
    if (e.status === 403) {
      isPrivate.value = true
    } else {
      notFound.value = true
    }
  }
})

function onFieldSaved(updated) {
  profile.value = { ...profile.value, ...updated }
  activeField.value = null
}

function onImagePicked(file) {
  setAdjustImage(file)
  adjustTarget.value = pickModalTarget.value
  adjustShape.value = profile.value.avatarShape || DEFAULT_AVATAR_SHAPE
  adjustError.value = ''
  pickModalTarget.value = null
}

function setAdjustImage(file) {
  adjustObjectUrl.value = URL.createObjectURL(file)
}

function cancelAdjust() {
  adjustObjectUrl.value = null
  adjustTarget.value = null
  adjustError.value = ''
}

async function confirmAdjust() {
  adjustSaving.value = true
  adjustError.value = ''
  try {
    // Avatar keeps a lossless PNG round-trip to the server (which also
    // derives a JPEG from it - see ProfileImageStore) - transparency
    // survives, so it needs no fill color. The background strip stays JPEG
    // only, which has no alpha channel at all - filling with the card's own
    // gradient first (same colors, same "no secondary set" -> solid
    // fallback as .card-background's CSS) means a transparent source image
    // ends up showing that gradient where it's see-through, not the plain
    // black a browser would otherwise composite against when flattening it.
    const dataUrl =
      adjustTarget.value === 'avatar'
        ? exportAdjustDataUrl(OUTPUT_WIDTH, 'image/png')
        : exportAdjustDataUrl(OUTPUT_WIDTH, 'image/jpeg', 0.9, [
            profile.value.mainColor || '#6c5ce7',
            profile.value.secondaryColor || profile.value.mainColor || '#6c5ce7',
          ])

    let updated =
      adjustTarget.value === 'avatar' ? await api.uploadAvatar(dataUrl) : await api.uploadBackground(dataUrl)

    // Shape is a separate field from the image itself, so it needs its own
    // PATCH - only sent when actually changed, to avoid a pointless write.
    if (adjustTarget.value === 'avatar' && adjustShape.value !== (profile.value.avatarShape || DEFAULT_AVATAR_SHAPE)) {
      updated = await api.updateMe({ avatarShape: adjustShape.value })
    }

    profile.value = { ...profile.value, ...updated }
    cancelAdjust()
  } catch (e) {
    adjustError.value = e.message
  } finally {
    adjustSaving.value = false
  }
}

function onFlagSaved(updated) {
  profile.value = { ...profile.value, ...updated }
  flagPickerField.value = null
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
    :class="{ 'container--has-backdrop': profile?.backgroundUrl }"
    :style="{
      '--card-maincolor': profile?.mainColor || '#6c5ce7',
      '--card-secondarycolor': profile?.secondaryColor || profile?.mainColor || '#6c5ce7',
      '--card-avatar-mask': avatarShapeMaskImage(activeAvatarShape),
    }"
  >
    <template v-if="notFound">
      <p>{{ t('profile.notFound') }}</p>
    </template>
    <template v-else-if="isPrivate">
      <p>{{ t('profile.private') }}</p>
    </template>
    <template v-else-if="profile">
      <p v-if="isOwner" class="edit-bar" :class="{ 'card-focus-dim': adjustTarget }">
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
          :class="{
            'card-background--adjusting': adjustTarget === 'background',
            'card-focus-dim': adjustTarget === 'avatar',
          }"
          :style="profile.backgroundUrl && adjustTarget !== 'background' ? { backgroundImage: `url(${profile.backgroundUrl}), ${CARD_BACKGROUND_GRADIENT_CSS}` } : null"
          @pointerdown="adjustTarget === 'background' && onAdjustDragStart($event)"
          @pointermove="adjustTarget === 'background' && onAdjustDragMove($event)"
          @pointerup="adjustTarget === 'background' && onAdjustDragEnd()"
          @pointercancel="adjustTarget === 'background' && onAdjustDragEnd()"
          :ref="(el) => adjustTarget === 'background' && setAdjustStage(el)"
        >
          <img
            v-if="adjustTarget === 'background'"
            :ref="setAdjustImageEl"
            :src="adjustObjectUrl"
            alt=""
            class="card-adjust-image"
            draggable="false"
            :style="{
              width: adjustDisplayWidth + 'px',
              height: adjustDisplayHeight + 'px',
              transform: `translate(${adjustOffsetX}px, ${adjustOffsetY}px)`,
            }"
            @load="onAdjustImageLoad"
          />
          <p v-if="adjustTarget === 'background'" class="card-adjust-hint">{{ t('profile.crop.dragHint') }}</p>
          <button
            v-if="editMode && !adjustTarget"
            type="button"
            class="card-image-edit-btn card-image-edit-btn--background"
            :aria-label="t('profile.editBackground')"
            :title="t('profile.editBackground')"
            @click="pickModalTarget = 'background'"
          >
            ✎
          </button>
        </div>
        <div
          class="card-avatar"
          :class="{
            'card-avatar--adjusting': adjustTarget === 'avatar',
            'card-focus-fade': adjustTarget === 'background',
          }"
        >
          <div
            class="card-avatar-shape"
            :class="{ 'card-avatar-shape--adjusting': adjustTarget === 'avatar' }"
            :style="profile.avatarUrl && adjustTarget !== 'avatar' ? { backgroundImage: `url(${profile.avatarUrl})` } : null"
            @pointerdown="adjustTarget === 'avatar' && onAdjustDragStart($event)"
            @pointermove="adjustTarget === 'avatar' && onAdjustDragMove($event)"
            @pointerup="adjustTarget === 'avatar' && onAdjustDragEnd()"
            @pointercancel="adjustTarget === 'avatar' && onAdjustDragEnd()"
            :ref="(el) => adjustTarget === 'avatar' && setAdjustStage(el)"
          >
            <img
              v-if="adjustTarget === 'avatar'"
              :ref="setAdjustImageEl"
              :src="adjustObjectUrl"
              alt=""
              class="card-adjust-image"
              draggable="false"
              :style="{
                width: adjustDisplayWidth + 'px',
                height: adjustDisplayHeight + 'px',
                transform: `translate(${adjustOffsetX}px, ${adjustOffsetY}px)`,
              }"
              @load="onAdjustImageLoad"
            />
          </div>
        </div>
        <!-- A sibling of .card-avatar, not a child of it: the avatar's own
             overflow: hidden + clip-path (needed to clip its shape - circle,
             hexagon, etc. - to match the saved avatarShape) would otherwise
             clip away this corner-positioned button along with it for any
             non-square shape, making it unclickable. -->
        <button
          v-if="editMode && !adjustTarget"
          type="button"
          class="card-image-edit-btn card-image-edit-btn--avatar"
          :aria-label="t('profile.editAvatar')"
          :title="t('profile.editAvatar')"
          @click="pickModalTarget = 'avatar'"
        >
          ✎
        </button>
        <div
          v-if="profile.flagLeftInner || profile.flagLeftOuter || editMode"
          class="flag-group flag-group--left"
        >
          <FlagSlot
            v-if="profile.flagLeftOuter || editMode"
            :flag-key="profile.flagLeftOuter"
            :edit-mode="editMode"
            :size="leftOuterIsBig ? 'big' : 'small'"
            :edit-label="t('profile.editFlagLeftOuter')"
            :dim="!!adjustTarget"
            @pick="flagPickerField = 'flagLeftOuter'"
          />
          <FlagSlot
            v-if="profile.flagLeftInner || editMode"
            :flag-key="profile.flagLeftInner"
            :edit-mode="editMode"
            size="big"
            :edit-label="t('profile.editFlagLeftInner')"
            :dim="!!adjustTarget"
            @pick="flagPickerField = 'flagLeftInner'"
          />
        </div>

        <div
          v-if="profile.flagRightInner || profile.flagRightOuter || editMode"
          class="flag-group flag-group--right"
        >
          <FlagSlot
            v-if="profile.flagRightInner || editMode"
            :flag-key="profile.flagRightInner"
            :edit-mode="editMode"
            size="big"
            :edit-label="t('profile.editFlagRightInner')"
            :dim="!!adjustTarget"
            @pick="flagPickerField = 'flagRightInner'"
          />
          <FlagSlot
            v-if="profile.flagRightOuter || editMode"
            :flag-key="profile.flagRightOuter"
            :edit-mode="editMode"
            :size="rightOuterIsBig ? 'big' : 'small'"
            :edit-label="t('profile.editFlagRightOuter')"
            :dim="!!adjustTarget"
            @pick="flagPickerField = 'flagRightOuter'"
          />
        </div>

        <div class="card-body" :class="{ 'card-focus-dim': adjustTarget }">
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

        <CardImageAdjustPanel
          v-if="adjustTarget"
          :type="adjustTarget"
          v-model:zoom="adjustZoom"
          v-model:shape="adjustShape"
          :saving="adjustSaving"
          :error="adjustError"
          @pick-different="setAdjustImage"
          @cancel="cancelAdjust"
          @confirm="confirmAdjust"
        />
      </div>

      <div
        v-if="editMode || belowLinks.length"
        class="links-section"
        :class="{ 'card-focus-dim': adjustTarget }"
      >
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

      <ImagePickModal
        v-if="pickModalTarget"
        :type="pickModalTarget"
        @close="pickModalTarget = null"
        @picked="onImagePicked"
      />

      <FlagPickerModal
        v-if="flagPickerField"
        :field="flagPickerField"
        :current-key="profile[flagPickerField]"
        @close="flagPickerField = null"
        @saved="onFlagSaved"
      />

      <DesignPickerModal
        v-if="designModalOpen"
        :main-color="profile.mainColor"
        :secondary-color="profile.secondaryColor"
        :avatar-shape="profile.avatarShape"
        @close="designModalOpen = false"
        @saved="onDesignSaved"
      />
    </template>
  </div>
</template>

<style scoped lang="scss" src="./ProfileView.scss"></style>
