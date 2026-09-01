// Optional flag badges shown on the profile card (see ProfileView.vue's
// .flag-slot--left/--right) – either a national flag or one of a curated
// set of pride/community flags. Both slots draw from the exact same pool.
//
// Country flags use the `flag-icons` package (real SVG flags via CSS
// classes) rather than Unicode flag emoji: Windows in particular renders
// flag emoji as plain two-letter text instead of an actual flag, so emoji
// aren't reliable enough for this. Pride/community flags use
// `@risadams/pride-flags` (pure-CSS gradients) for the same reason emoji
// don't cover them at all. Both are imported globally in main.js.

// The standard ISO 3166-1 alpha-2 list of officially assigned country
// codes, plus a couple of flag-icons' non-ISO extras (eu, un). Names aren't
// hardcoded here – Intl.DisplayNames resolves each code to a name in the
// current UI language at render time (see getCountryFlags()), so this never
// goes stale/needs translating by hand.
const COUNTRY_CODES = [
  'AD', 'AE', 'AF', 'AG', 'AI', 'AL', 'AM', 'AO', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AW', 'AX', 'AZ',
  'BA', 'BB', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ', 'BL', 'BM', 'BN', 'BO', 'BQ', 'BR', 'BS',
  'BT', 'BV', 'BW', 'BY', 'BZ', 'CA', 'CC', 'CD', 'CF', 'CG', 'CH', 'CI', 'CK', 'CL', 'CM', 'CN',
  'CO', 'CR', 'CU', 'CV', 'CW', 'CX', 'CY', 'CZ', 'DE', 'DJ', 'DK', 'DM', 'DO', 'DZ', 'EC', 'EE',
  'EG', 'EH', 'ER', 'ES', 'ET', 'EU', 'FI', 'FJ', 'FK', 'FM', 'FO', 'FR', 'GA', 'GB', 'GD', 'GE',
  'GF', 'GG', 'GH', 'GI', 'GL', 'GM', 'GN', 'GP', 'GQ', 'GR', 'GS', 'GT', 'GU', 'GW', 'GY', 'HK',
  'HM', 'HN', 'HR', 'HT', 'HU', 'ID', 'IE', 'IL', 'IM', 'IN', 'IO', 'IQ', 'IR', 'IS', 'IT', 'JE',
  'JM', 'JO', 'JP', 'KE', 'KG', 'KH', 'KI', 'KM', 'KN', 'KP', 'KR', 'KW', 'KY', 'KZ', 'LA', 'LB',
  'LC', 'LI', 'LK', 'LR', 'LS', 'LT', 'LU', 'LV', 'LY', 'MA', 'MC', 'MD', 'ME', 'MF', 'MG', 'MH',
  'MK', 'ML', 'MM', 'MN', 'MO', 'MP', 'MQ', 'MR', 'MS', 'MT', 'MU', 'MV', 'MW', 'MX', 'MY', 'MZ',
  'NA', 'NC', 'NE', 'NF', 'NG', 'NI', 'NL', 'NO', 'NP', 'NR', 'NU', 'NZ', 'OM', 'PA', 'PE', 'PF',
  'PG', 'PH', 'PK', 'PL', 'PM', 'PN', 'PR', 'PS', 'PT', 'PW', 'PY', 'QA', 'RE', 'RO', 'RS', 'RU',
  'RW', 'SA', 'SB', 'SC', 'SD', 'SE', 'SG', 'SH', 'SI', 'SJ', 'SK', 'SL', 'SM', 'SN', 'SO', 'SR',
  'SS', 'ST', 'SV', 'SX', 'SY', 'SZ', 'TC', 'TD', 'TF', 'TG', 'TH', 'TJ', 'TK', 'TL', 'TM', 'TN',
  'TO', 'TR', 'TT', 'TV', 'TW', 'TZ', 'UA', 'UG', 'UN', 'US', 'UY', 'UZ', 'VA', 'VC', 'VE', 'VG',
  'VI', 'VN', 'VU', 'WF', 'WS', 'YE', 'YT', 'ZA', 'ZM', 'ZW',
]

// A few flag-icons codes aren't valid Intl.DisplayNames region codes (the
// "European Union"/"United Nations" pseudo-flags) – named by hand since
// DisplayNames.of() would just throw/return the raw code for these.
const NON_REGION_NAMES = {
  EU: { en: 'Europe', de: 'Europa' },
  UN: { en: 'United Nations', de: 'Vereinte Nationen' },
}

let cachedCountryFlags = null
let cachedLocale = null

/** @returns {{ key: string, label: string, type: 'country', iconClass: string }[]} */
export function getCountryFlags(locale = 'en') {
  if (cachedLocale === locale && cachedCountryFlags) return cachedCountryFlags

  const displayNames = new Intl.DisplayNames([locale], { type: 'region' })
  cachedCountryFlags = COUNTRY_CODES.map((code) => {
    const lower = code.toLowerCase()
    const label = NON_REGION_NAMES[code]?.[locale] || NON_REGION_NAMES[code]?.en || displayNames.of(code) || code
    return { key: 'country:' + lower, label, type: 'country', iconClass: 'fi-' + lower }
  }).sort((a, b) => a.label.localeCompare(b.label, locale))
  cachedLocale = locale

  return cachedCountryFlags
}

// Pride/community flags – class names from @risadams/pride-flags (see
// node_modules/@risadams/pride-flags/dist/pride-flags.css for the full set
// this was picked from).
export const SPECIAL_FLAGS = [
  { key: 'pride:traditional', label: 'LGBTQ+ / Pride', type: 'pride', iconClass: 'traditional' },
  { key: 'pride:progress', label: 'Progress Pride', type: 'pride', iconClass: 'progress' },
  { key: 'pride:bisexual', label: 'Bisexual', type: 'pride', iconClass: 'bisexual' },
  { key: 'pride:gay-men', label: 'Gay', type: 'pride', iconClass: 'gay-men' },
  { key: 'pride:lesbian', label: 'Lesbian', type: 'pride', iconClass: 'lesbian' },
  { key: 'pride:pansexual', label: 'Pansexual', type: 'pride', iconClass: 'pansexual' },
  { key: 'pride:transgender', label: 'Transgender', type: 'pride', iconClass: 'transgender' },
  { key: 'pride:nonbinary', label: 'Non-binary', type: 'pride', iconClass: 'nonbinary' },
  { key: 'pride:genderfluid', label: 'Genderfluid', type: 'pride', iconClass: 'genderfluid' },
  { key: 'pride:genderqueer', label: 'Genderqueer', type: 'pride', iconClass: 'genderqueer' },
  { key: 'pride:asexual', label: 'Asexual', type: 'pride', iconClass: 'asexual' },
  { key: 'pride:aromantic', label: 'Aromantic', type: 'pride', iconClass: 'aromantic' },
  { key: 'pride:intersex', label: 'Intersex', type: 'pride', iconClass: 'intersex' },
  { key: 'pride:polyamory', label: 'Polyamory', type: 'pride', iconClass: 'polyamory' },
  { key: 'pride:bear-brotherhood', label: 'Bear Pride', type: 'pride', iconClass: 'bear-brotherhood' },
  { key: 'pride:leather', label: 'Leather / Fetish', type: 'pride', iconClass: 'leather' },
  { key: 'pride:rubber', label: 'Rubber', type: 'pride', iconClass: 'rubber' },
  { key: 'pride:puppy', label: 'Puppy Play', type: 'pride', iconClass: 'puppy' },
]

const SPECIAL_BY_KEY = Object.fromEntries(SPECIAL_FLAGS.map((f) => [f.key, f]))

/** @returns {{ key: string, label: string, type: string, iconClass: string } | null} */
export function getFlag(key, locale = 'en') {
  if (!key) return null
  if (key.startsWith('country:')) {
    return getCountryFlags(locale).find((f) => f.key === key) || null
  }
  return SPECIAL_BY_KEY[key] || null
}
