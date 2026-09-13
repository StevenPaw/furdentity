<?php

namespace App\Api\Support;

/**
 * Rejects a profile link URL that doesn't point to an actual domain on the
 * public internet - mirrors frontend/src/utils/socialPlatforms.js's
 * isValidLinkUrl(), keep both in sync.
 *
 * Without this, a bare word like "s" (no scheme, no dot) would still get
 * accepted as a URL, and a plain `<a href="s">` resolves that against the
 * *current page* rather than failing - e.g. turning into
 * furdentity.com/id/s instead of an obviously broken link. The frontend
 * already rejects this before submitting, but that's only ever advisory -
 * this is the actual enforcement, since a client can call the API directly.
 */
class LinkUrlValidator
{
    public static function isValid(string $url): bool
    {
        $trimmed = trim($url);

        if ($trimmed === '') {
            return false;
        }

        if (preg_match('/^mailto:/i', $trimmed) || preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $trimmed)) {
            return true;
        }

        $withScheme = preg_match('/^https?:\/\//i', $trimmed) ? $trimmed : "https://{$trimmed}";
        $host = parse_url($withScheme, PHP_URL_HOST);

        if (!is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower((string) preg_replace('/^www\./', '', $host));

        return $host !== 'localhost' && str_contains($host, '.');
    }
}
