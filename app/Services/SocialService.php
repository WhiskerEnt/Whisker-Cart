<?php
namespace App\Services;

use Core\Database;
use Core\View;

/**
 * WHISKER — Floating contact and social bar
 *
 * A column of links pinned to the side of the storefront that stays put as
 * the shopper scrolls. Every link is optional; the bar only appears once the
 * shopkeeper has filled at least one in.
 *
 * WhatsApp and phone are entered as numbers and turned into links here, since
 * shopkeepers reach for a phone number rather than a wa.me URL. Everything
 * else is a full profile URL, checked against View::isSafeUrl() so a pasted
 * javascript: URL cannot reach an href.
 */
class SocialService
{
    /**
     * key => [label, brand colour, kind]
     *
     * 'number' becomes a wa.me or tel: link; 'email' a mailto:; 'url' is used
     * as given.
     */
    private const CHANNELS = [
        'whatsapp'  => ['WhatsApp',  '#25D366', 'number'],
        'phone'     => ['Call us',   '#0f766e', 'number'],
        'email'     => ['Email us',  '#6366f1', 'email'],
        'facebook'  => ['Facebook',  '#1877F2', 'url'],
        'instagram' => ['Instagram', '#E4405F', 'url'],
        'telegram'  => ['Telegram',  '#26A5E4', 'url'],
        'x'         => ['X',         '#000000', 'url'],
        'youtube'   => ['YouTube',   '#FF0000', 'url'],
        'tiktok'    => ['TikTok',    '#010101', 'url'],
        'linkedin'  => ['LinkedIn',  '#0A66C2', 'url'],
    ];

    /** @return string[] setting keys the admin form may write */
    public static function settingKeys(): array
    {
        $keys = ['social_enabled', 'social_position', 'social_display', 'social_whatsapp_text'];
        foreach (array_keys(self::CHANNELS) as $c) {
            $keys[] = 'social_' . $c;
        }
        return $keys;
    }

    public static function channels(): array
    {
        return self::CHANNELS;
    }

    public static function enabled(): bool
    {
        return Database::setting('social', 'social_enabled', '0') === '1';
    }

    /**
     * True when the channels hide behind a single button until asked for.
     * Defaults to showing them, which is what a shop turning this on wants.
     */
    public static function collapsed(): bool
    {
        return Database::setting('social', 'social_display', 'always') === 'collapsed';
    }

    /** 'left' or 'right'. Right shares an edge with the chat bubble, so left is the default. */
    public static function position(): string
    {
        return Database::setting('social', 'social_position', 'left') === 'right' ? 'right' : 'left';
    }

    /**
     * The links to render, in the order the channels are declared.
     *
     * @return array<int,array{key:string,label:string,color:string,url:string}>
     */
    public static function links(): array
    {
        if (!self::enabled()) return [];

        $out = [];
        foreach (self::CHANNELS as $key => [$label, $color, $kind]) {
            $raw = trim((string) Database::setting('social', 'social_' . $key, ''));
            if ($raw === '') continue;

            $url = self::buildUrl($key, $kind, $raw);
            if ($url === null || !View::isSafeUrl($url)) continue;

            $out[] = ['key' => $key, 'label' => $label, 'color' => $color, 'url' => $url];
        }
        return $out;
    }

    private static function buildUrl(string $key, string $kind, string $raw): ?string
    {
        if ($kind === 'number') {
            // Keep only digits and a leading +, so "+91 98765 43210" and
            // "(880) 157-744" both work.
            $digits = preg_replace('/[^0-9]/', '', $raw);
            if ($digits === '' || strlen($digits) < 6) return null;

            if ($key === 'whatsapp') {
                $text = trim((string) Database::setting('social', 'social_whatsapp_text', ''));
                return 'https://wa.me/' . $digits . ($text !== '' ? '?text=' . rawurlencode($text) : '');
            }
            return 'tel:+' . $digits;
        }

        if ($kind === 'email') {
            return filter_var($raw, FILTER_VALIDATE_EMAIL) ? 'mailto:' . $raw : null;
        }

        // Anything already declaring a scheme other than http(s) is refused
        // rather than having https:// pasted in front of it, which would turn
        // "file:///etc/passwd" into a valid-looking link to a host named file.
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $raw) && !preg_match('#^https?://#i', $raw)) {
            return null;
        }

        // A shopkeeper pasting "facebook.com/shop" means https://.
        if (!preg_match('#^https?://#i', $raw)) {
            if (str_starts_with($raw, '/') || str_contains($raw, ' ')) return null;
            $raw = 'https://' . $raw;
        }
        return filter_var($raw, FILTER_VALIDATE_URL) ? $raw : null;
    }

    /**
     * Inline SVG for a channel. Kept here rather than as image files so the
     * bar costs no extra requests and inherits currentColor.
     */
    public static function icon(string $key): string
    {
        $paths = [
            'whatsapp'  => '<path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2m0 1.67c2.2 0 4.27.86 5.82 2.42a8.2 8.2 0 0 1 2.42 5.82c0 4.54-3.7 8.24-8.25 8.24-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.2 8.2 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.26-8.24m-3.6 4.03c-.17 0-.44.06-.67.31-.23.25-.88.86-.88 2.1s.9 2.43 1.03 2.6c.13.17 1.77 2.7 4.29 3.79.6.26 1.07.41 1.43.53.6.19 1.15.16 1.58.1.48-.07 1.48-.6 1.69-1.19.21-.58.21-1.08.15-1.19-.06-.1-.23-.16-.48-.29-.25-.12-1.48-.73-1.71-.81-.23-.09-.4-.13-.56.12-.17.25-.64.81-.79.98-.14.16-.29.19-.54.06-.25-.12-1.05-.39-2-1.23-.74-.66-1.24-1.47-1.38-1.72-.15-.25-.02-.38.11-.51.11-.11.25-.29.37-.44.13-.14.17-.25.25-.41.09-.17.04-.31-.02-.43-.06-.13-.55-1.37-.77-1.87-.2-.48-.4-.42-.55-.42h-.47z"/>',
            'phone'     => '<path d="M6.62 10.79a15.05 15.05 0 0 0 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02z"/>',
            'email'     => '<path d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2m0 4-8 5-8-5V6l8 5 8-5z"/>',
            'facebook'  => '<path d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5 3.66 9.15 8.44 9.94v-7H7.9v-2.9h2.54V9.85c0-2.51 1.49-3.9 3.77-3.9 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.78-1.63 1.57v1.88h2.78l-.45 2.9h-2.33v7C18.34 21.21 22 17.06 22 12.06"/>',
            'instagram' => '<path d="M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9s.68.82.9 1.38c.16.42.36 1.06.41 2.23.06 1.27.07 1.64.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38s-.82.68-1.38.9c-.42.16-1.06.36-2.23.41-1.27.06-1.64.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41-.56-.22-.96-.48-1.38-.9s-.68-.82-.9-1.38c-.16-.42-.36-1.06-.41-2.23C2.17 15.58 2.16 15.2 2.16 12s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38s.82-.68 1.38-.9c.42-.16 1.06-.36 2.23-.41C8.42 2.17 8.8 2.16 12 2.16M12 0C8.74 0 8.33.01 7.05.07 5.78.13 4.9.33 4.14.63a5.9 5.9 0 0 0-2.13 1.38A5.9 5.9 0 0 0 .63 4.14c-.3.76-.5 1.64-.56 2.91C.01 8.33 0 8.74 0 12s.01 3.67.07 4.95c.06 1.27.26 2.15.56 2.91.3.79.72 1.46 1.38 2.13a5.9 5.9 0 0 0 2.13 1.38c.76.3 1.64.5 2.91.56C8.33 23.99 8.74 24 12 24s3.67-.01 4.95-.07c1.27-.06 2.15-.26 2.91-.56a5.9 5.9 0 0 0 2.13-1.38 5.9 5.9 0 0 0 1.38-2.13c.3-.76.5-1.64.56-2.91.06-1.28.07-1.69.07-4.95s-.01-3.67-.07-4.95c-.06-1.27-.26-2.15-.56-2.91a5.9 5.9 0 0 0-1.38-2.13A5.9 5.9 0 0 0 19.86.63c-.76-.3-1.64-.5-2.91-.56C15.67.01 15.26 0 12 0m0 5.84a6.16 6.16 0 1 0 0 12.32 6.16 6.16 0 0 0 0-12.32M12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8m7.85-10.4a1.44 1.44 0 1 1-2.88 0 1.44 1.44 0 0 1 2.88 0"/>',
            'telegram'  => '<path d="M11.94 15.4 8.2 19.1c-.4 0-.34-.15-.48-.53l-1.2-3.96-3.1-.93c-.67-.2-.67-.67.15-1L20.3 5.2c.55-.25 1.08.13.87.98l-3.2 15.1c-.15.7-.57.87-1.15.54l-3.17-2.34-1.53 1.48c-.17.17-.31.31-.63.31z"/>',
            'x'         => '<path d="M18.9 2H22l-6.77 7.74L23 22h-6.23l-4.88-6.38L6.3 22H3.2l7.24-8.28L2 2h6.4l4.4 5.82zm-1.09 18.14h1.72L7.28 3.77H5.44z"/>',
            'youtube'   => '<path d="M23 12s0-3.87-.49-5.72a2.99 2.99 0 0 0-2.1-2.12C18.55 3.66 12 3.66 12 3.66s-6.55 0-8.4.5A2.99 2.99 0 0 0 1.5 6.3C1 8.13 1 12 1 12s0 3.87.49 5.72c.27 1 1.07 1.8 2.1 2.07 1.86.5 8.41.5 8.41.5s6.55 0 8.4-.5a2.99 2.99 0 0 0 2.11-2.07C23 15.87 23 12 23 12M9.9 15.5v-7l5.5 3.5z"/>',
            'tiktok'    => '<path d="M16.6 5.82A4.28 4.28 0 0 1 15.54 3h-3.09v12.4a2.59 2.59 0 0 1-2.59 2.5 2.59 2.59 0 1 1 .77-5.06V9.7a5.68 5.68 0 0 0-.77-.05A5.66 5.66 0 1 0 15.54 15V8.99a7.34 7.34 0 0 0 4.28 1.38V7.28a4.29 4.29 0 0 1-3.22-1.46"/>',
            'linkedin'  => '<path d="M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.41v1.56h.05a3.74 3.74 0 0 1 3.37-1.85c3.6 0 4.27 2.37 4.27 5.46zM5.34 7.43a2.07 2.07 0 1 1 0-4.13 2.07 2.07 0 0 1 0 4.13m1.78 13.02H3.55V9h3.57zM22.22 0H1.77C.79 0 0 .77 0 1.72v20.56C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.72V1.72C24 .77 23.2 0 22.22 0"/>',
        ];

        $d = $paths[$key] ?? '';
        return $d === '' ? '' : '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">' . $d . '</svg>';
    }
}
