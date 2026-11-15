<?php
namespace Tests\Unit;

use App\Services\ShowcaseService;
use PHPUnit\Framework\TestCase;

/**
 * The homepage slider and product pictures. The fill order itself needs a
 * database and is checked in tests/ci/integration.php.
 */
class ShowcaseTest extends TestCase
{
    private function source(string $rel): string
    {
        return (string) file_get_contents(WK_ROOT . '/' . $rel);
    }

    public function testBothHomepagesTakeTheSliderFromTheShowcase(): void
    {
        foreach (['views/store/home-v1.php', 'views/store/home-v2.php'] as $view) {
            $src = $this->source($view);
            $this->assertStringContainsString("\$carouselProducts = \$showcase['products'] ?? [];", $src, $view);
            $this->assertStringNotContainsString("fn(\$p) => \$p['is_featured']", $src, "$view still shows only ticked products");
            $this->assertStringContainsString("partials/showcase-note.php", $src, $view);
        }
        $this->assertStringContainsString("'showcase'     => \\App\\Services\\ShowcaseService::pick(),", $this->source('app/Controllers/Store/HomeController.php'));
    }

    public function testTheNoteIsForTheShopkeeperOnly(): void
    {
        $note = $this->source('views/store/partials/showcase-note.php');
        $this->assertStringStartsWith('<?php', $note);
        $this->assertMatchesRegularExpression('/if \(!\\\\Core\\\\Session::isAdmin\(\)/', $note, 'shoppers never see it');
    }

    public function testDescriptionsReadNaturally(): void
    {
        $empty = ['products' => [], 'chosen' => 0, 'auto' => ['bestseller' => 0, 'recent' => 0, 'sale' => 0, 'daily' => 0]];
        $this->assertStringContainsString('no products in stock', ShowcaseService::describe($empty));

        $mine = ['products' => [1, 2], 'chosen' => 2, 'auto' => ['bestseller' => 0, 'recent' => 0, 'sale' => 0, 'daily' => 0]];
        $this->assertSame('The homepage slider shows the 2 products you chose.', ShowcaseService::describe($mine));

        $auto = ['products' => range(1, 6), 'chosen' => 0, 'auto' => ['bestseller' => 2, 'recent' => 3, 'sale' => 0, 'daily' => 1]];
        $this->assertSame(
            "The homepage slider is choosing for you: 2 best-selling, 3 recently added and 1 today's pick.",
            ShowcaseService::describe($auto)
        );

        $mixed = ['products' => range(1, 6), 'chosen' => 1, 'auto' => ['bestseller' => 0, 'recent' => 5, 'sale' => 0, 'daily' => 0]];
        $this->assertSame('The homepage slider shows the 1 product you chose, then 5 recently added to fill it.', ShowcaseService::describe($mixed));
    }

    public function testTheTickboxSaysWhatItDoes(): void
    {
        foreach (['views/admin/products/create.php', 'views/admin/products/edit.php'] as $form) {
            $src = $this->source($form);
            $this->assertStringContainsString('Show in homepage slider', $src, $form);
            $this->assertStringNotContainsString('> Featured</label>', $src, "$form still says only Featured");
            $card = strpos($src, '<h2>Organization</h2>');
            $this->assertLessThan(strpos($src, 'name="category_id"', $card), strpos($src, 'name="is_featured"', $card), "$form: the tickbox comes first in its card");
        }
    }

    public function testProductPicturesShowWholeUnlessTheShopChoosesToFill(): void
    {
        $css = $this->source('assets/css/store.css');
        $this->assertStringContainsString('.wk-product-img img { width: 100%; height: 100%; object-fit: contain;', $css);
        $this->assertStringContainsString('.wk-img-fill .wk-product-img img', $css);

        foreach (glob(WK_ROOT . '/views/store/{*,*/*}.php', GLOB_BRACE) as $view) {
            $this->assertStringNotContainsString('object-fit:cover', (string) file_get_contents($view), basename($view) . ' crops a product picture inline');
        }

        $layout = $this->source('views/store/layouts/main.php');
        $this->assertStringContainsString("Database::setting('general', 'image_fit') === 'cover' ? ' class=\"wk-img-fill\"'", $layout);
        $this->assertStringContainsString("'image_fit'", $this->source('app/Controllers/Admin/SettingsController.php'), 'the setting is on the save list');
    }
}
