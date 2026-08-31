import { createI18n } from 'vue-i18n'
import en from './locales/en.json'
import de from './locales/de.json'

export const SUPPORTED_LOCALES = ['en', 'de']
const DEFAULT_LOCALE = 'en'
const STORAGE_KEY = 'furdentity.locale'

function detectLocale() {
  const stored = localStorage.getItem(STORAGE_KEY)
  if (SUPPORTED_LOCALES.includes(stored)) return stored

  return DEFAULT_LOCALE
}

const initialLocale = detectLocale()

const i18n = createI18n({
  legacy: false,
  locale: initialLocale,
  fallbackLocale: DEFAULT_LOCALE,
  messages: { en, de },
})

document.documentElement.lang = initialLocale

export function setLocale(locale) {
  if (!SUPPORTED_LOCALES.includes(locale)) return
  i18n.global.locale.value = locale
  document.documentElement.lang = locale
  localStorage.setItem(STORAGE_KEY, locale)
}

export default i18n
