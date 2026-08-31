<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { api } from '../api/client'

const { t } = useI18n()
const profiles = ref([])
const error = ref('')

const me = ref(null)
const titleInput = ref('')
const bioInput = ref('')
const editError = ref('')
const savingMe = ref(false)
const savedMe = ref(false)

async function loadProfiles() {
  try {
    profiles.value = (await api.internalProfiles()).data
  } catch (e) {
    error.value = e.message
  }
}

async function loadMe() {
  try {
    me.value = await api.me()
    titleInput.value = me.value.title
    bioInput.value = me.value.bio
  } catch (e) {
    editError.value = e.message
  }
}

async function saveMe() {
  savingMe.value = true
  editError.value = ''
  savedMe.value = false
  try {
    me.value = await api.updateMe({ title: titleInput.value, bio: bioInput.value })
    savedMe.value = true
    await loadProfiles()
  } catch (e) {
    editError.value = e.message
  } finally {
    savingMe.value = false
  }
}

onMounted(() => {
  loadProfiles()
  loadMe()
})
</script>

<template>
  <div class="container">
    <h2>{{ t('home.myProfileHeading') }}</h2>

    <form v-if="me" @submit.prevent="saveMe">
      <label for="handle">{{ t('register.handle') }}</label>
      <input id="handle" :value="me.handle" type="text" disabled />
      <p class="hint">{{ t('home.handleFixed') }}</p>

      <label for="title">{{ t('register.username') }}</label>
      <input id="title" v-model="titleInput" type="text" required />

      <label for="bio">{{ t('home.bio') }}</label>
      <textarea id="bio" v-model="bioInput" rows="4"></textarea>

      <p v-if="editError" style="color: crimson">{{ editError }}</p>
      <p v-else-if="savedMe" class="hint">{{ t('home.saved') }}</p>

      <p style="margin-top: 1rem">
        <button type="submit" :disabled="savingMe">
          {{ savingMe ? t('login.submitting') : t('home.save') }}
        </button>
      </p>
    </form>

    <h2>{{ t('home.profilesHeading') }}</h2>
    <p v-if="error" style="color: crimson">{{ error }}</p>

    <ul>
      <li v-for="profile in profiles" :key="profile.id">
        <strong>{{ profile.title }}</strong>
        <span v-if="profile.handle"> — {{ profile.handle }}</span>
      </li>
      <li v-if="!profiles.length && !error">{{ t('home.noProfiles') }}</li>
    </ul>
  </div>
</template>

<style scoped lang="scss" src="./HomeView.scss"></style>
