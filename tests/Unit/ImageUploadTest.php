<?php
namespace Tests\Unit;

use App\Services\ImageService;
use Core\View;
use PHPUnit\Framework\TestCase;

/**
 * Large phone photos: accepted, turned the right way up, kept sharp, and
 * served at a sensible size. The GD work itself is exercised on a server
 * with GD; these pin down the parts that need none.
 */
class ImageUploadTest extends TestCase
{
    private array $made = [];

    protected function tearDown(): void
    {
        foreach ($this->made as $f) @unlink($f);
    }

    private function source(string $rel): string
    {
        return (string) file_get_contents(WK_ROOT . '/' . $rel);
    }

    /** A JPEG header carrying an EXIF orientation, in either byte order. */
    private function jpegWithOrientation(int $orientation, bool $little): string
    {
        $tiff = $little
            ? "II\x2A\x00\x08\x00\x00\x00" . "\x01\x00" . "\x12\x01\x03\x00\x01\x00\x00\x00" . pack('v', $orientation) . "\x00\x00" . "\x00\x00\x00\x00"
            : "MM\x00\x2A\x00\x00\x00\x08" . "\x00\x01" . "\x01\x12\x00\x03\x00\x00\x00\x01" . pack('n', $orientation) . "\x00\x00" . "\x00\x00\x00\x00";
        $app1 = "\xFF\xE1" . pack('n', strlen($tiff) + 8) . "Exif\x00\x00" . $tiff;
        $path = tempnam(sys_get_temp_dir(), 'wkjpg');
        file_put_contents($path, "\xFF\xD8" . $app1 . "\xFF\xDA\x00\x02" . str_repeat("\x00", 16) . "\xFF\xD9");
        return $this->made[] = $path;
    }

    public function testThePhonesTurnIsReadWithoutTheExifExtension(): void
    {
        foreach ([1, 3, 6, 8] as $o) {
            $this->assertSame($o, ImageService::orientation($this->jpegWithOrientation($o, false)), "big-endian $o");
            $this->assertSame($o, ImageService::orientation($this->jpegWithOrientation($o, true)), "little-endian $o");
        }
    }

    public function testAFileWithoutATurnIsTakenAsUpright(): void
    {
        $plain = tempnam(sys_get_temp_dir(), 'wkjpg');
        file_put_contents($plain, "\xFF\xD8\xFF\xDA\x00\x02" . str_repeat("\x00", 16) . "\xFF\xD9");
        $this->made[] = $plain;
        $this->assertSame(1, ImageService::orientation($plain));

        $notJpeg = tempnam(sys_get_temp_dir(), 'wkpng');
        file_put_contents($notJpeg, "\x89PNG\r\n\x1a\n");
        $this->made[] = $notJpeg;
        $this->assertSame(1, ImageService::orientation($notJpeg));

        $this->assertSame(1, ImageService::orientation($this->jpegWithOrientation(9, false)), 'a nonsense value is ignored');
    }

    public function testServerLimitsAreReadAsPhpWritesThem(): void
    {
        $this->assertSame(2 * 1024 * 1024, ImageService::iniBytes('2M'));
        $this->assertSame(512 * 1024, ImageService::iniBytes('512K'));
        $this->assertSame(1024 ** 3, ImageService::iniBytes('1G'));
        $this->assertSame(0, ImageService::iniBytes('-1'));
        $this->assertSame('2 MB', ImageService::humanBytes(2 * 1024 * 1024));
        $this->assertSame('1.5 MB', ImageService::humanBytes((int) (1.5 * 1024 * 1024)));
        $this->assertLessThanOrEqual(ImageService::MAX_UPLOAD_BYTES, ImageService::maxUploadBytes());
        $this->assertSame(20 * 1024 * 1024, ImageService::MAX_UPLOAD_BYTES, '10 to 15 MB phone photos are within the limit');
    }

    public function testQualityAndSizeAreHighEnoughToZoomInto(): void
    {
        $src = $this->source('app/Services/ImageService.php');
        $this->assertSame(2400, ImageService::MAX_EDGE);
        $this->assertSame(800, ImageService::CARD_EDGE);
        $this->assertStringContainsString('private const WEBP_QUALITY = 90;', $src);
        $this->assertStringContainsString("(\$ext === 'png' && defined('IMG_WEBP_LOSSLESS')) ? IMG_WEBP_LOSSLESS", $src, 'graphics keep every pixel');
        $this->assertStringContainsString('self::uprighted($source, self::orientation($path))', $src);
        $this->assertStringContainsString('if (!self::makeRoomFor($path)) return $fallback();', $src);
    }

    public function testTheBrowserAndTheServerAgreeOnTheLongestEdge(): void
    {
        $js = $this->source('assets/js/admin.js');
        $this->assertMatchesRegularExpression('/MAX_EDGE: ' . ImageService::MAX_EDGE . ',/', $js);
        $this->assertStringContainsString("createImageBitmap(file, { imageOrientation: 'from-image' })", $js, 'the browser turns it the right way up');
        $this->assertStringContainsString("encode('image/webp', quality)", $js);
        $this->assertStringContainsString('<meta name="wk-max-upload"', $this->source('views/admin/layouts/main.php'));
    }

    public function testNoPictureIsTurnedAwaySilentlyOrByAFixedFiveMegabytes(): void
    {
        foreach (['views/admin/products/create.php', 'views/admin/products/edit.php'] as $form) {
            $src = $this->source($form);
            $this->assertStringNotContainsString('5*1024*1024', $src, $form);
            $this->assertStringContainsString('Whisker.prepareImage(', $src, $form);
        }
        $edit = $this->source('views/admin/products/edit.php');
        $this->assertStringContainsString("Whisker.toast(original.name+' is '+Whisker.sizeLabel(file.size)", $edit, 'a refusal is said out loud');

        $controller = $this->source('app/Controllers/Admin/ProductController.php');
        $this->assertStringNotContainsString('5 * 1024 * 1024', $controller);
        $this->assertSame(2, substr_count($controller, '> \\App\\Services\\ImageService::MAX_UPLOAD_BYTES'), 'both uploaders use the shared limit');
        $this->assertStringContainsString('UPLOAD_ERR_INI_SIZE   => self::tooLargeMessage(),', $controller);
    }

    public function testDeletingAPictureDeletesItsCopies(): void
    {
        $this->assertStringNotContainsString('@unlink', $this->source('app/Controllers/Admin/ProductController.php'));
        $this->assertStringContainsString('ImageService::deleteFiles($img[\'image_path\'])', $this->source('app/Services/VariantService.php'));

        $dir = WK_ROOT . '/storage/uploads/products/';
        $base = 'wk_unit_' . bin2hex(random_bytes(4));
        foreach (['.jpg', '.webp', '-800.webp'] as $suffix) {
            file_put_contents($dir . $base . $suffix, 'x');
            $this->made[] = $dir . $base . $suffix;
        }
        ImageService::deleteFiles($base . '.jpg');
        foreach (['.jpg', '.webp', '-800.webp'] as $suffix) {
            $this->assertFileDoesNotExist($dir . $base . $suffix);
        }
    }

    /** A PNG header claiming a size, which is all getimagesize reads. */
    private function pngHeader(int $w, int $h): string
    {
        $ihdr = pack('NNCCCCC', $w, $h, 8, 6, 0, 0, 0);
        return "\x89PNG\r\n\x1a\n" . pack('N', 13) . 'IHDR' . $ihdr . pack('N', crc32('IHDR' . $ihdr));
    }

    public function testCardsAreOfferedTheSmallCopy(): void
    {
        $dir = WK_ROOT . '/storage/uploads/products/';
        $base = 'wk_unit_' . bin2hex(random_bytes(4));
        file_put_contents($dir . $base . '.png', $this->pngHeader(2400, 1600));
        file_put_contents($dir . $base . '.webp', 'x');
        file_put_contents($dir . $base . '-800.webp', 'x');
        array_push($this->made, $dir . $base . '.png', $dir . $base . '.webp', $dir . $base . '-800.webp');

        $html = View::productImage($base . '.png', 'Test', ['sizes' => '220px']);
        $this->assertStringContainsString($base . '-800.webp 800w, ', $html);
        $this->assertStringContainsString($base . '.webp 2400w', $html);
        $this->assertStringContainsString('sizes="220px"', $html);
        $this->assertStringContainsString('width="2400" height="1600"', $html, 'the space is still held open');
        $this->assertSame($base . '-800.webp', ImageService::cardName($base . '.png'));
        $this->assertSame($base . '.webp', ImageService::bestName($base . '.png'));
    }

    public function testThePictureWithoutACardCopyIsServedAsBefore(): void
    {
        $dir = WK_ROOT . '/storage/uploads/products/';
        $base = 'wk_unit_' . bin2hex(random_bytes(4));
        file_put_contents($dir . $base . '.png', $this->pngHeader(700, 700));
        file_put_contents($dir . $base . '.webp', 'x');
        array_push($this->made, $dir . $base . '.png', $dir . $base . '.webp');

        $html = View::productImage($base . '.png', 'Test');
        $this->assertStringContainsString('<source type="image/webp" srcset="', $html);
        $this->assertStringNotContainsString(' 800w', $html);
        $this->assertNull(ImageService::cardName($base . '.png'));
    }
}
