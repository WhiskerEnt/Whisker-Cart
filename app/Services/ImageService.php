<?php
namespace App\Services;

use Core\Database;

/**
 * What happens to a picture between the shopkeeper choosing it and a shopper
 * seeing it.
 *
 * Done once at upload rather than on every page view:
 *
 *  - Turn it the right way up. A phone stores a portrait photo sideways and
 *    records the turn in the file's metadata; redrawing discards that, so the
 *    turn is applied first.
 *  - Re-encode it. A file can be a valid image and a valid script at the same
 *    time; redrawing it through GD keeps the pixels and discards everything
 *    else, metadata included.
 *  - Keep it large enough to zoom into, and no larger: the longest edge is
 *    brought down to MAX_EDGE.
 *  - Write a WebP beside it at a quality that cannot be told apart from the
 *    original. Graphics with flat colour and transparency (PNG) get lossless
 *    WebP, which keeps every pixel exactly.
 *  - Write a smaller WebP for product cards, so a grid of pictures does not
 *    download the zoom-sized ones.
 *
 * The original stays, so a browser that cannot read WebP is served what it
 * was before. Nothing here is required: on a host with no GD the upload still
 * succeeds and the shop serves the file as it arrived.
 *
 * Large photos are usually made smaller in the shopkeeper's browser before
 * they are sent (see Whisker.prepareImage in assets/js/admin.js), so they fit
 * under any host's upload limit. This class does not depend on that.
 */
class ImageService
{
    /** Longest edge kept: sharp when zoomed on a large screen. */
    public const MAX_EDGE = 2400;

    /** Longest edge of the copy product cards use. */
    public const CARD_EDGE = 800;

    /** The largest file an upload may be, whatever the server allows. */
    public const MAX_UPLOAD_BYTES = 20 * 1024 * 1024;

    /** High enough that the difference cannot be seen. */
    private const WEBP_QUALITY = 90;
    private const JPEG_QUALITY = 92;

    /** Bytes GD needs per pixel while a picture is open, with headroom. */
    private const BYTES_PER_PIXEL = 5;

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
        if (!self::makeRoomFor($path)) return $fallback();

        $source = self::load($path, $ext);
        if (!$source) return $fallback();

        try {
            if ($ext === 'jpg' || $ext === 'jpeg') {
                $source = self::uprighted($source, self::orientation($path));
            }

            $width  = imagesx($source);
            $height = imagesy($source);

            // Only ever downwards: enlarging a small picture makes a bigger
            // file and a blurrier image.
            $scale = min(1, self::MAX_EDGE / max($width, $height));
            $target = $source;
            if ($scale < 1) {
                $target = self::resized($source, $ext, $scale);
                $width  = imagesx($target);
                $height = imagesy($target);
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
                $webp = self::writeWebp($target, self::webpPath($path), $ext) === true;

                $cardScale = self::CARD_EDGE / max($width, $height);
                if ($webp && $cardScale < 1) {
                    $card = self::resized($target, $ext, $cardScale);
                    self::writeWebp($card, self::cardPath($path), $ext);
                    imagedestroy($card);
                }
            }

            if ($target !== $source) imagedestroy($target);
            return ['width' => $width, 'height' => $height, 'webp' => $webp];
        } catch (\Throwable $e) {
            error_log('Whisker: preparing an uploaded image failed: ' . $e->getMessage());
            return $fallback();
        } finally {
            if (is_object($source) || is_resource($source)) @imagedestroy($source);
        }
    }

    /**
     * The largest upload this server will take, in bytes: the smallest of
     * PHP's two limits and Whisker's own.
     */
    public static function maxUploadBytes(): int
    {
        $limits = [self::MAX_UPLOAD_BYTES];
        foreach (['upload_max_filesize', 'post_max_size'] as $key) {
            $bytes = self::iniBytes((string) ini_get($key));
            // Room for the rest of the form on the way in.
            if ($key === 'post_max_size' && $bytes > 0) $bytes -= 64 * 1024;
            if ($bytes > 0) $limits[] = $bytes;
        }
        return max(1, min($limits));
    }

    /** "2M", "512K", "1G" or plain bytes, as php.ini writes sizes. */
    public static function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1' || $value === '0') return 0;
        $unit = strtolower(substr($value, -1));
        $number = (float) $value;
        return (int) match ($unit) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /** A size for people: "2 MB", "20 MB", "512 KB". */
    public static function humanBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) return rtrim(rtrim(number_format($bytes / 1024 / 1024, 1), '0'), '.') . ' MB';
        return max(1, (int) round($bytes / 1024)) . ' KB';
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

        // Not recorded: an image from before the sizes were stored, or one
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

    /** The card-sized WebP beside an image, if the image was big enough to need one. */
    public static function cardName(string $filename): ?string
    {
        $filename = basename(trim($filename));
        if ($filename === '') return null;
        $card = preg_replace('/\.[^.]+$/', '', $filename) . '-' . self::CARD_EDGE . '.webp';
        return is_file(self::uploadDir() . $card) ? $card : null;
    }

    /**
     * Address of the best copy for a script to put in an img src: the WebP if
     * there is one, otherwise the original.
     */
    public static function bestName(string $filename): string
    {
        return self::webpName($filename) ?? basename(trim($filename));
    }

    /** Remove an image and every copy made of it. */
    public static function deleteFiles(string $filename): void
    {
        $filename = basename(trim($filename));
        if ($filename === '') return;
        $dir = self::uploadDir();
        $base = preg_replace('/\.[^.]+$/', '', $filename);
        foreach ([$filename, $base . '.webp', $base . '-' . self::CARD_EDGE . '.webp'] as $file) {
            if (is_file($dir . $file)) @unlink($dir . $file);
        }
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

    /**
     * Which way up a JPEG was taken, from the Orientation tag in its EXIF
     * data: 1 is upright, 3 upside down, 6 and 8 a quarter turn, and 2, 4, 5
     * and 7 the mirrored versions. Read directly from the file, because the
     * exif extension is often not installed.
     */
    public static function orientation(string $path): int
    {
        $fh = @fopen($path, 'rb');
        if (!$fh) return 1;
        try {
            if (fread($fh, 2) !== "\xFF\xD8") return 1;
            while (!feof($fh)) {
                $marker = fread($fh, 2);
                if (strlen($marker) < 2 || $marker[0] !== "\xFF") return 1;
                $len = unpack('n', (string) fread($fh, 2))[1] ?? 0;
                if ($len < 2) return 1;
                if ($marker[1] === "\xE1") {
                    $data = (string) fread($fh, $len - 2);
                    return substr($data, 0, 6) === "Exif\0\0" ? self::orientationFromTiff(substr($data, 6)) : 1;
                }
                // Image data starts here; any EXIF comes before it.
                if ($marker[1] === "\xDA") return 1;
                fseek($fh, $len - 2, SEEK_CUR);
            }
            return 1;
        } finally {
            fclose($fh);
        }
    }

    // ── Internals ────────────────────────────────────────────────────────

    private static function orientationFromTiff(string $tiff): int
    {
        if (strlen($tiff) < 8) return 1;
        $little = substr($tiff, 0, 2) === 'II';
        $short = fn(int $at) => unpack($little ? 'v' : 'n', substr($tiff, $at, 2))[1] ?? 0;
        $long  = fn(int $at) => unpack($little ? 'V' : 'N', substr($tiff, $at, 4))[1] ?? 0;

        $ifd = $long(4);
        if ($ifd + 2 > strlen($tiff)) return 1;
        $count = $short($ifd);
        for ($i = 0; $i < $count; $i++) {
            $entry = $ifd + 2 + $i * 12;
            if ($entry + 12 > strlen($tiff)) return 1;
            if ($short($entry) === 0x0112) {
                $value = $short($entry + 8);
                return ($value >= 1 && $value <= 8) ? $value : 1;
            }
        }
        return 1;
    }

    /** The picture turned and flipped so that orientation 1 describes it. */
    private static function uprighted($image, int $orientation)
    {
        if ($orientation <= 1 || $orientation > 8) return $image;

        // imagerotate turns anticlockwise, so 270 is a quarter turn clockwise.
        $angle = match ($orientation) {
            3       => 180,
            5, 6    => 270,
            7, 8    => 90,
            default => 0,
        };
        if ($angle !== 0) {
            $turned = imagerotate($image, $angle, 0);
            if (!$turned) return $image;
            imagedestroy($image);
            $image = $turned;
        }

        // The mirrored orientations, flipped after any turn.
        if (function_exists('imageflip')) {
            if (in_array($orientation, [2, 5, 7], true)) imageflip($image, IMG_FLIP_HORIZONTAL);
            if ($orientation === 4) imageflip($image, IMG_FLIP_VERTICAL);
        }
        return $image;
    }

    /**
     * Raise the memory limit when a large picture needs it, if the host
     * allows. Opening a 6000 x 4000 photo takes over 100 MB, more than many
     * hosts give a request by default.
     */
    private static function makeRoomFor(string $path): bool
    {
        $size = @getimagesize($path);
        if (!$size || $size[0] < 1 || $size[1] < 1) return true;

        // The original, a resized copy and the card copy can be open at once.
        $needed = (int) ($size[0] * $size[1] * self::BYTES_PER_PIXEL * 1.6) + memory_get_usage();
        $limit = self::iniBytes((string) ini_get('memory_limit'));
        if ($limit <= 0 || $needed <= $limit) return true;

        $want = (int) ceil($needed / (1024 * 1024)) + 32;
        if (@ini_set('memory_limit', $want . 'M') !== false && self::iniBytes((string) ini_get('memory_limit')) >= $needed) {
            return true;
        }

        error_log('Whisker: ' . basename($path) . ' is ' . $size[0] . ' x ' . $size[1]
            . ' and needs about ' . self::humanBytes($needed) . ' of memory to resize; the server allows '
            . ini_get('memory_limit') . '. It was stored as uploaded.');
        return false;
    }

    private static function resized($image, string $ext, float $scale)
    {
        $newW = max(1, (int) round(imagesx($image) * $scale));
        $newH = max(1, (int) round(imagesy($image) * $scale));
        $resized = imagecreatetruecolor($newW, $newH);
        self::keepTransparency($resized, $ext);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, imagesx($image), imagesy($image));
        return $resized;
    }

    /** Photos at high quality; flat graphics (PNG) losslessly, keeping every pixel. */
    private static function writeWebp($image, string $path, string $ext): bool
    {
        $quality = ($ext === 'png' && defined('IMG_WEBP_LOSSLESS')) ? IMG_WEBP_LOSSLESS : self::WEBP_QUALITY;
        return @imagewebp($image, $path, $quality) === true;
    }

    private static function uploadDir(): string
    {
        return WK_ROOT . '/storage/uploads/products/';
    }

    private static function webpPath(string $path): string
    {
        return preg_replace('/\.[^.]+$/', '', $path) . '.webp';
    }

    private static function cardPath(string $path): string
    {
        return preg_replace('/\.[^.]+$/', '', $path) . '-' . self::CARD_EDGE . '.webp';
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
