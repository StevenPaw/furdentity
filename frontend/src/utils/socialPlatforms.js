import iconBehance from '../assets/social/logo_behance.png'
import iconDiscord from '../assets/social/logo_discord.png'
import iconDribbble from '../assets/social/logo_dribbble.png'
import iconFacebook from '../assets/social/logo_facebook.png'
import iconFlickr from '../assets/social/logo_flickr.png'
import iconFuraffinity from '../assets/social/logo_furaffinity.svg'
import iconGithub from '../assets/social/logo_github.png'
import iconInstagram from '../assets/social/logo_instagram.png'
import iconItchio from '../assets/social/logo_itchio.png'
import iconKakaotalk from '../assets/social/logo_kakaotalk.png'
import iconLine from '../assets/social/logo_line.png'
import iconLinkedin from '../assets/social/logo_linkedin.png'
import iconMail from '../assets/social/logo_mail.png'
import iconMessenger from '../assets/social/logo_messenger.png'
import iconPinterest from '../assets/social/logo_pinterest.png'
import iconReddit from '../assets/social/logo_reddit.png'
import iconSkype from '../assets/social/logo_skype.png'
import iconSnapchat from '../assets/social/logo_snapchat.png'
import iconSoundcloud from '../assets/social/logo_soundcloud.png'
import iconSpotify from '../assets/social/logo_spotify.png'
import iconTelegram from '../assets/social/logo_telegram.png'
import iconTiktok from '../assets/social/logo_tiktok.png'
import iconTinder from '../assets/social/logo_tinder.png'
import iconTumblr from '../assets/social/logo_tumblr.png'
import iconTwitch from '../assets/social/logo_twitch.png'
import iconTwitter from '../assets/social/logo_twitter.png'
import iconVimeo from '../assets/social/logo_vimeo.png'
import iconWebsite from '../assets/social/logo_website.png'
import iconWechat from '../assets/social/logo_wechat.png'
import iconWhatsapp from '../assets/social/logo_whatsapp.png'
import iconYoutube from '../assets/social/logo_youtube.png'

// Ordered for the picker dropdown. `hosts` are matched against the URL's
// hostname (without "www.") to auto-detect a platform from a pasted link.
export const PLATFORMS = [
  { key: 'website', label: 'Website', icon: iconWebsite, hosts: [] },
  { key: 'instagram', label: 'Instagram', icon: iconInstagram, hosts: ['instagram.com'] },
  { key: 'twitter', label: 'Twitter / X', icon: iconTwitter, hosts: ['twitter.com', 'x.com'] },
  { key: 'tiktok', label: 'TikTok', icon: iconTiktok, hosts: ['tiktok.com'] },
  { key: 'facebook', label: 'Facebook', icon: iconFacebook, hosts: ['facebook.com', 'fb.com'] },
  { key: 'youtube', label: 'YouTube', icon: iconYoutube, hosts: ['youtube.com', 'youtu.be'] },
  { key: 'twitch', label: 'Twitch', icon: iconTwitch, hosts: ['twitch.tv'] },
  { key: 'discord', label: 'Discord', icon: iconDiscord, hosts: ['discord.com', 'discord.gg'] },
  { key: 'github', label: 'GitHub', icon: iconGithub, hosts: ['github.com'] },
  { key: 'linkedin', label: 'LinkedIn', icon: iconLinkedin, hosts: ['linkedin.com'] },
  { key: 'reddit', label: 'Reddit', icon: iconReddit, hosts: ['reddit.com'] },
  { key: 'pinterest', label: 'Pinterest', icon: iconPinterest, hosts: ['pinterest.com', 'pin.it'] },
  { key: 'tumblr', label: 'Tumblr', icon: iconTumblr, hosts: ['tumblr.com'] },
  { key: 'snapchat', label: 'Snapchat', icon: iconSnapchat, hosts: ['snapchat.com'] },
  { key: 'whatsapp', label: 'WhatsApp', icon: iconWhatsapp, hosts: ['whatsapp.com', 'wa.me'] },
  { key: 'telegram', label: 'Telegram', icon: iconTelegram, hosts: ['t.me', 'telegram.me', 'telegram.org'] },
  { key: 'messenger', label: 'Messenger', icon: iconMessenger, hosts: ['messenger.com', 'm.me'] },
  { key: 'skype', label: 'Skype', icon: iconSkype, hosts: ['skype.com'] },
  { key: 'line', label: 'LINE', icon: iconLine, hosts: ['line.me'] },
  { key: 'wechat', label: 'WeChat', icon: iconWechat, hosts: ['wechat.com', 'weixin.qq.com'] },
  { key: 'kakaotalk', label: 'KakaoTalk', icon: iconKakaotalk, hosts: ['kakao.com', 'pf.kakao.com'] },
  { key: 'tinder', label: 'Tinder', icon: iconTinder, hosts: ['tinder.com'] },
  { key: 'spotify', label: 'Spotify', icon: iconSpotify, hosts: ['spotify.com'] },
  { key: 'soundcloud', label: 'SoundCloud', icon: iconSoundcloud, hosts: ['soundcloud.com'] },
  { key: 'vimeo', label: 'Vimeo', icon: iconVimeo, hosts: ['vimeo.com'] },
  { key: 'flickr', label: 'Flickr', icon: iconFlickr, hosts: ['flickr.com'] },
  { key: 'dribbble', label: 'Dribbble', icon: iconDribbble, hosts: ['dribbble.com'] },
  { key: 'behance', label: 'Behance', icon: iconBehance, hosts: ['behance.net'] },
  { key: 'furaffinity', label: 'Fur Affinity', icon: iconFuraffinity, hosts: ['furaffinity.net'] },
  { key: 'itchio', label: 'itch.io', icon: iconItchio, hosts: ['itch.io'] },
  { key: 'mail', label: 'E-Mail', icon: iconMail, hosts: [] },
]

const PLATFORM_BY_KEY = Object.fromEntries(PLATFORMS.map((p) => [p.key, p]))

export function getPlatform(key) {
  return PLATFORM_BY_KEY[key] || PLATFORM_BY_KEY.website
}

/** Guesses a platform key from a pasted URL, falling back to 'website' (or 'mail' for mailto: links). */
export function detectPlatform(url) {
  const trimmed = (url || '').trim()

  if (/^mailto:/i.test(trimmed) || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trimmed)) {
    return 'mail'
  }

  let hostname
  try {
    hostname = new URL(/^https?:\/\//i.test(trimmed) ? trimmed : `https://${trimmed}`).hostname
  } catch {
    return 'website'
  }

  hostname = hostname.replace(/^www\./, '').toLowerCase()

  const match = PLATFORMS.find((p) => p.hosts.some((host) => hostname === host || hostname.endsWith(`.${host}`)))
  return match?.key || 'website'
}
