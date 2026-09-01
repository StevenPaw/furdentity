<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { api } from '../api/client'
import { PLATFORMS, detectPlatform, getPlatform } from '../utils/socialPlatforms'

const props = defineProps({
  link: { type: Object, default: null }, // null => creating a new link
  placement: { type: String, default: 'below' }, // only used when creating (link === null)
})
const emit = defineEmits(['close', 'saved', 'deleted'])

const { t } = useI18n()

const url = ref(props.link?.url || '')
const title = ref(props.link?.title || '')
const platform = ref(props.link?.platform || 'website')
const platformTouched = ref(Boolean(props.link))
const titleTouched = ref(Boolean(props.link?.title))

const saving = ref(false)
const deleting = ref(false)
const error = ref('')

function applyPlatform(key) {
  platform.value = key
  if (!titleTouched.value) {
    title.value = getPlatform(key).label
  }
}

function onUrlInput() {
  if (platformTouched.value) return
  applyPlatform(detectPlatform(url.value))
}

function onPlatformChange() {
  platformTouched.value = true
  applyPlatform(platform.value)
}

function onTitleInput() {
  titleTouched.value = true
}

async function save() {
  if (!url.value.trim()) {
    error.value = t('profile.linkUrlRequired')
    return
  }

  saving.value = true
  error.value = ''
  try {
    const data = { url: url.value.trim(), title: title.value.trim(), platform: platform.value }
    const saved = props.link
      ? await api.updateLink(props.link.id, data)
      : await api.createLink({ ...data, placement: props.placement })
    emit('saved', saved)
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}

async function remove() {
  if (!props.link) return

  deleting.value = true
  error.value = ''
  try {
    await api.deleteLink(props.link.id)
    emit('deleted', props.link.id)
  } catch (e) {
    error.value = e.message
    deleting.value = false
  }
}
</script>

<template>
  <div class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal" role="dialog" aria-modal="true" :aria-label="t('profile.linkModalTitle')">
      <h3 class="modal-heading">{{ t('profile.linkModalTitle') }}</h3>

      <label for="link-url">{{ t('profile.linkUrl') }}</label>
      <input id="link-url" v-model="url" type="text" placeholder="https://…" autofocus @input="onUrlInput" />

      <label for="link-title">{{ t('profile.linkTitle') }}</label>
      <input
        id="link-title"
        v-model="title"
        type="text"
        :placeholder="t('profile.linkTitlePlaceholder')"
        @input="onTitleInput"
      />

      <label for="link-platform">{{ t('profile.linkPlatform') }}</label>
      <div class="platform-picker">
        <img :src="getPlatform(platform).icon" alt="" class="platform-picker_icon" />
        <select id="link-platform" v-model="platform" @change="onPlatformChange">
          <option v-for="p in PLATFORMS" :key="p.key" :value="p.key">{{ p.label }}</option>
        </select>
      </div>

      <p v-if="error" class="modal-error">{{ error }}</p>

      <div class="modal-actions">
        <button
          v-if="link"
          type="button"
          class="modal-delete"
          :disabled="deleting || saving"
          @click="remove"
        >
          {{ deleting ? t('login.submitting') : t('profile.delete') }}
        </button>
        <span class="modal-actions_spacer"></span>
        <button type="button" class="modal-cancel" @click="$emit('close')">
          {{ t('profile.cancel') }}
        </button>
        <button type="button" :disabled="saving || deleting" @click="save">
          {{ saving ? t('login.submitting') : t('profile.save') }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss" src="./LinkEditModal.scss"></style>
