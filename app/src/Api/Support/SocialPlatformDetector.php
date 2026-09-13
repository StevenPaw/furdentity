<?php

namespace App\Api\Support;

/**
 * Detects a {@see \App\Model\ProfileLink}'s platform (and therefore its
 * displayed icon) from its URL - mirrors frontend/src/utils/socialPlatforms.js's
 * PLATFORMS/hosts table, keep both in sync.
 *
 * This is the *only* place Platform is ever set from - {@see
 * \App\Api\InternalApiController::createLink()} and ::linkItem() never trust
 * a client-supplied platform value at all, so a link's icon can never be
 * spoofed to claim a destination (e.g. "Instagram") the URL doesn't actually
 * point to.
 */
class SocialPlatformDetector
{
    private const array HOSTS = [
        'instagram' => ['instagram.com'],
        'twitter' => ['twitter.com', 'x.com'],
        'tiktok' => ['tiktok.com'],
        'facebook' => ['facebook.com', 'fb.com'],
        'youtube' => ['youtube.com', 'youtu.be'],
        'twitch' => ['twitch.tv'],
        'discord' => ['discord.com', 'discord.gg'],
        'github' => ['github.com'],
        'linkedin' => ['linkedin.com'],
        'reddit' => ['reddit.com'],
        'pinterest' => ['pinterest.com', 'pin.it'],
        'tumblr' => ['tumblr.com'],
        'snapchat' => ['snapchat.com'],
        'whatsapp' => ['whatsapp.com', 'wa.me'],
        'telegram' => ['t.me', 'telegram.me', 'telegram.org'],
        'messenger' => ['messenger.com', 'm.me'],
        'skype' => ['skype.com'],
        'line' => ['line.me'],
        'wechat' => ['wechat.com', 'weixin.qq.com'],
        'kakaotalk' => ['kakao.com', 'pf.kakao.com'],
        'tinder' => ['tinder.com'],
        'spotify' => ['spotify.com'],
        'soundcloud' => ['soundcloud.com'],
        'vimeo' => ['vimeo.com'],
        'flickr' => ['flickr.com'],
        'dribbble' => ['dribbble.com'],
        'behance' => ['behance.net'],
        'furaffinity' => ['furaffinity.net'],
        'itchio' => ['itch.io'],
    ];

    public static function detect(string $url): string
    {
        $trimmed = trim($url);

        if (preg_match('/^mailto:/i', $trimmed) || preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $trimmed)) {
            return 'mail';
        }

        $withScheme = preg_match('/^https?:\/\//i', $trimmed) ? $trimmed : "https://{$trimmed}";
        $host = parse_url($withScheme, PHP_URL_HOST);

        if (!is_string($host) || $host === '') {
            return 'website';
        }

        $host = strtolower((string) preg_replace('/^www\./', '', $host));

        foreach (self::HOSTS as $platform => $hosts) {
            foreach ($hosts as $candidate) {
                if ($host === $candidate || str_ends_with($host, ".{$candidate}")) {
                    return $platform;
                }
            }
        }

        return 'website';
    }
}
