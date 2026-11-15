<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Interface icons are drawn from the icon set, not typed as emoji, which
 * render differently on every platform. Email keeps its emoji because mail
 * clients strip inline SVG, and chatbot replies are conversation rather
 * than interface. Arrows, check marks and rating stars are typography and
 * are not counted.
 */
class InterfaceIconsTest extends TestCase
{
    private const EMOJI = '/[\x{1F300}-\x{1FAFF}\x{1F000}-\x{1F0FF}\x{2B50}\x{2705}\x{274C}\x{2139}\x{23ED}\x{2600}-\x{2604}\x{2607}-\x{26FF}]/u';

    /** Files whose emoji are email content or conversation, not interface. */
    private const NOT_INTERFACE = [
        'app/Controllers/Admin/EmailTemplateController.php',
        'app/Controllers/Store/ChatbotController.php',
        'app/Services/EmailService.php',
    ];

    /** Lines in interface files that build email content. */
    private const EMAIL_LINE = '/status_emoji|statusEmoji|>🔑<\/div>|>✅<\/div>/u';

    private function files(string ...$dirs): array
    {
        $out = [];
        foreach ($dirs as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(WK_ROOT . '/' . $dir));
            foreach ($it as $file) {
                if (preg_match('/\.(php|js)$/', $file->getFilename())) {
                    $out[] = str_replace('\\', '/', substr((string) $file, strlen(WK_ROOT) + 1));
                }
            }
        }
        sort($out);
        return $out;
    }

    public function testNoInterfaceCodeTypesAnEmoji(): void
    {
        $offenders = [];
        foreach ($this->files('views', 'assets/js', 'install', 'app/Controllers', 'app/Services', 'core') as $rel) {
            if (in_array($rel, self::NOT_INTERFACE, true)) continue;
            foreach (file(WK_ROOT . '/' . $rel) as $n => $line) {
                if (preg_match(self::EMOJI, $line) && !preg_match(self::EMAIL_LINE, $line)) {
                    $offenders[] = $rel . ':' . ($n + 1) . '  ' . trim($line);
                }
            }
        }
        $this->assertSame([], $offenders, "Use \$icon() instead:\n" . implode("\n", $offenders));
    }

    public function testEmailContentKeepsItsEmoji(): void
    {
        $src = (string) file_get_contents(WK_ROOT . '/app/Controllers/Admin/EmailTemplateController.php');
        $this->assertMatchesRegularExpression(self::EMOJI, $src, 'mail clients strip SVG, so email templates keep emoji');
    }

    /**
     * Inline styles are being retired in favour of classes. The count may
     * only fall: lower the ceiling when it does, and never raise it.
     */
    public function testInlineStylesOnlyGoDown(): void
    {
        $ceilings = ['views/store' => 550, 'views/admin' => 767, 'views/install' => 35];
        foreach ($ceilings as $dir => $ceiling) {
            $count = 0;
            foreach ($this->files($dir) as $rel) {
                $count += preg_match_all('/\bstyle="/', (string) file_get_contents(WK_ROOT . '/' . $rel));
            }
            $this->assertLessThanOrEqual($ceiling, $count, "$dir has $count inline style attributes; the ceiling is $ceiling. Use a class.");
        }
    }
}
