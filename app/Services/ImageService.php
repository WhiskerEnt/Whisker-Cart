<?php
namespace App\Services;

use Core\Database;

/**
 * What happens to a picture between the shopkeeper choosing it and a shopper
 * seeing it.
 *
 * Three jobs, all done once at upload rather than on every page view:
 *
 *  - Re-encode it. A file can be a valid image and a valid script at the same
 *    time; redrawing it through GD keeps the pixels and discards everything
 *    else, metadata included.
 *  - Bring it down to a sensible size. A photo straight off a phone is several
 *    thousand pixels wide and is shown in a card a few hundred wide.
 *  - Write a WebP alongside it, which is usually a third of the size for the
 *    same picture. The original stays, so a browser that cannot read WebP is
 *    served exactly what it was before.
 *
 * Nothing here is required: on a host with no GD the upload still succeeds and
 * the shop serves the file as it arrived.
 */
class ImageService
{
    /** Nothing in a shop needs to be wider than this. */
    public const MAX_EDGE = 1600;

    /** High enough that the difference is not visible at these sizes. */
    private const WEBP_QUALITY = 82;
    private const JPEG_QUALITY = 88;

    private static array $dimensionCache = [];

    /**
     * Prepare an uploaded file for the shop.
     *
     * @return array{width:int,height:int,webp:bool} the size as stored, and
     *         whether a WebP copy now sits beside it
     */
    public static function process(string $path, string $ext): array
    {
        $fallback = static function () use ($path): array {
            $size = @getimagesize($path);
            return ['width' => (int) ($size[0] ?? 0), 'height' => (int) ($size[1] ?? 0), 'webp' => false];
        };

        if (!extension_loaded('gd') || !is_file($path)) return $fallback();

        $source = self::load($path, $ext);
        if (!$source) return $fallback();

        try {
            $width  = imagesx($source);
            $height = imagesy($source);

            // Only ever downwards: enlarging a small picture makes a bigger
            // file and a blurrier image.
            $scale = min(1, self::MAX_EDGE / max($width, $height));
            $target = $source;
            if ($scale < 1) {
                $newW = max(1, (int) round($width * $scale));
                $newH = max(1, (int) round($height * $scale));
                $resized = imagecreatetruecolor($newW, $newH);
                self::keepTransparency($resized, $ext);
                imagecopyresampled($resized, $source, 0, 0, 0, 0, $newW, $newH, $width, $height);
                $target = $resized;
                $width  = $newW;
                $height = $newH;
            } elseif ($ext !== 'gif') {
                // Not being resized, but still redrawn so nothing rides along
                // inside the file.
                $clean = imagecreatetruecolor($width, $height);
                self::keepTransparency($clean, $ext);
                imagecopy($clean, $source, 0, 0, 0, 0, $width, $height);
                $target = $clean;
            }

            self::write($target, $path, $ext);

            // An animated GIF would lose its animation, so it is left alone.
            $webp = false;
            if ($ext !== 'gif' && function_exists('imagewebp')) {
                $webp = @imagewebp($target, self::webpPath($path), self::WEBP_QUALITY) === true;
            }

            if ($target !== $source) imagedestroy($target);
            return ['width' => $width, 'height' => $height, 'webp' => $webp];
        } catch (\Throwable $e) {
            error_log('Whisker: preparing an uploaded image failed — ' . $e->getMessage());
            return $fallback();
        } finally {
            if (is_object($source) || is_resource($source)) @imagedestroy($source);
        }
    }

    /**
     * The stored size of an image, so the page can hold its space open before
     * the picture arrives.
     *
     * @return array{0:int,1:int}|null width and height
     */
    public static function dimensions(string $filename): ?array
    {
        $filename = basename(trim($filename));
        if ($filename === '') return null;
        if (array_key_exists($filename, self::$dimensionCache)) return self::$dimensionCache[$filename];

        self::loadKnownDimensions();
        if (array_key_exists($filename, self::$dimensionCache)) return self::$dimensionCache[$filename];

        // Not recorded — an image from before the sizes were stored, or one
        // put in place by hand. Read it from the file the once.
        $path = self::uploadDir() . $filename;
        $size = is_file($path) ? @getimagesize($path) : false;
        $dims = ($size && $size[0] > 0) ? [(int) $size[0], (int) $size[1]] : null;

        return self::$dimensionCache[$filename] = $dims;
    }

    /** The WebP sitting beside an image, if one was made. */
    public static function webpName(string $filename): ?string
    {
        $filename = basename(trim($filename));
        if ($filename === '') return null;
        $webp = preg_replace('/\.[^.]+$/', '', $filename) . '.webp';
        return is_file(self::uploadDir() . $webp) ? $webp : null;
    }

    /**
     * Bring images uploaded before any of this existed up to date.
     *
     * @return array{done:int,skipped:int,failed:int}
     */
    public static function backfill(): array
    {
        $out = ['done' => 0, 'skipped' => 0, 'failed' => 0];
        $dir = self::uploadDir();
        if (!is_dir($dir)) return $out;

        foreach (glob($dir . '*.{jpg,jpeg,png,gif}', GLOB_BRACE) ?: [] as $path) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($ext === 'jpeg') $ext = 'jpg';

            if (is_file(self::webpPath($path))) { $out['skipped']++; continue; }

            $result = self::process($path, $ext);
            if ($result['width'] > 0) {
                self::remember(basename($path), $result['width'], $result['height']);
                $out['done']++;
            } else {
                $out['failed']++;
            }
        }
        return $out;
    }

    /** Record a size against the stored image, where there is a row for it. */
    public static function remember(string $filename, int $width, int $height): void
    {
        if ($width < 1 || $height < 1) return;
        try {
            Database::query(
                "UPDATE wk_product_images SET width = ?, height = ? WHERE image_path = ?",
                [$width, $height, basename($filename)]
            );
        } catch (\Throwable $e) {
            // The columns arrive with a migration; until then sizes are read
            // from the files themselves.
        }
        self::$dimensionCache[basename($filename)] = [$width, $height];
    }

    // ── Internals ────────────────────────────────────────────────────────

    private static function uploadDir(): string
    {
        return WK_ROOT . '/storage/uploads/products/';
    }

    private static function webpPath(string $path): string
    {
        return preg_replace('/\.[^.]+$/', '', $path) . '.webp';
    }

    /** One query for every size the shop knows, kept for the rest of the request. */
    private static function loadKnownDimensions(): void
    {
        static $loaded = false;
        if ($loaded) return;
        $loaded = true;

        try {
            $rows = Database::fetchAll(
                "SELECT image_path, width, height FROM wk_product_images WHERE width > 0 AND height > 0"
            );
            foreach ($rows as $row) {
                self::$dimensionCache[basename($row['image_path'])] = [(int) $row['width'], (int) $row['height']];
            }
        } catch (\Throwable $e) {
            // No columns yet, or no database in this context.
        }
    }

    private static function load(string $path, string $ext)
    {
        return match ($ext) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($path),
            'png'         => @imagecreatefrompng($path),
            'webp'        => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            'gif'         => @imagecreatefromgif($path),
            default       => false,
        };
    }

    private static function write($image, string $path, string $ext): bool
    {
        return match ($ext) {
            'jpg', 'jpeg' => @imagejpeg($image, $path, self::JPEG_QUALITY),
            'png'         => @imagepng($image, $path, 8),
            'webp'        => function_exists('imagewebp') ? @imagewebp($image, $path, self::WEBP_QUALITY) : false,
            'gif'         => @imagegif($image, $path),
            default       => false,
        };
    }

    /** A logo on a transparent background must not come back on black. */
    private static function keepTransparency($image, string $ext): void
    {
        if ($ext !== 'png' && $ext !== 'gif' && $ext !== 'webp') return;
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefilledrectangle($image, 0, 0, imagesx($image), imagesy($image), $transparent);
        imagealphablending($image, true);
    }
}
