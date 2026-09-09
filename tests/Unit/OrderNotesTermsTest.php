<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Checkout collected an address and a card and nothing else — no way to say
 * "leave it with the neighbour", and no record that anybody agreed to the
 * terms the shop publishes.
 */
class OrderNotesTermsTest extends TestCase
{
    private function checkout(): string
    {
        return (string) file_get_contents(WK_ROOT . '/app/Controllers/Store/CheckoutController.php');
    }

    private function view(): string
    {
        return (string) file_get_contents(WK_ROOT . '/views/store/checkout.php');
    }

    // ── Delivery notes ───────────────────────────────────────────────────

    /**
     * wk_orders.notes is a JSON blob the shopkeeper writes carrier and
     * tracking into. A note from the customer sharing that column would be
     * overwritten by the next status update.
     */
    public function testTheCustomersNoteHasItsOwnColumn(): void
    {
        $files = glob(WK_ROOT . '/sql/migrations/*order_notes_terms.sql');
        $this->assertNotEmpty($files, 'nothing adds the columns');

        $sql = (string) file_get_contents($files[0]);
        $this->assertStringContainsString('customer_note', $sql);
        $this->assertStringContainsString('terms_accepted_at', $sql);

        $checkout = $this->checkout();
        $this->assertStringContainsString("\$orderData['customer_note']", $checkout);
        $this->assertStringNotContainsString("\$orderData['notes'] = \$note", $checkout,
            'the customer must not be writing into the shopkeeper\'s column');
    }

    public function testTheNoteIsOptionalAndBounded(): void
    {
        $this->assertStringContainsString('name="customer_note"', $this->view());
        $this->assertStringContainsString('maxlength="500"', $this->view());
        $this->assertStringNotContainsString('name="customer_note" required', $this->view());

        $this->assertStringContainsString('mb_substr($note, 0, 500)', $this->checkout(),
            'the browser limit is a courtesy; the server has to hold the line');
        $this->assertStringContainsString("if (\$note !== '')", $this->checkout(),
            'an empty note should not be stored as an empty string');
    }

    /** A note nobody reads is not worth collecting. */
    public function testTheNoteReachesTheShopkeeperAndComesBackToTheCustomer(): void
    {
        $admin = (string) file_get_contents(WK_ROOT . '/views/admin/orders/show.php');
        $this->assertStringContainsString("\$o['customer_note']", $admin,
            'the person packing the order never sees what was asked for');

        $mine = (string) file_get_contents(WK_ROOT . '/views/store/account/order-detail.php');
        $this->assertStringContainsString("\$o['customer_note']", $mine,
            'the customer cannot check what they wrote');
    }

    /** Line breaks in an instruction are part of the instruction. */
    public function testTheNoteKeepsItsShapeWhenShown(): void
    {
        foreach (['/views/admin/orders/show.php', '/views/store/account/order-detail.php'] as $rel) {
            $src = (string) file_get_contents(WK_ROOT . $rel);
            // From the guard forward to where it is actually printed — the
            // first mention is the emptiness check, not the paragraph.
            $at = strpos($src, "\$o['customer_note']");
            $around = substr($src, $at, 600);
            $this->assertStringContainsString('white-space:pre-wrap', $around, "{$rel} collapses the note onto one line");
            $this->assertStringContainsString('$e(', $around, "{$rel} prints the note without escaping it");
        }
    }

    // ── Terms ────────────────────────────────────────────────────────────

    /**
     * Whether somebody is asked to agree is decided by whether the shop has
     * published anything to agree to — not by a setting that can say yes while
     * pointing at a page that does not exist.
     */
    public function testTheBoxAppearsOnlyWhenThereAreTermsToAgreeTo(): void
    {
        $fn = substr($this->checkout(), strpos($this->checkout(), 'private static function termsPage('));
        $this->assertStringContainsString("is_active = 1", $fn, 'an unpublished page must not be linked');
        $this->assertStringContainsString("slug LIKE '%terms%'", $fn);

        $this->assertStringContainsString('<?php if ($wkTerms): ?>', $this->view(),
            'a shop with no terms would show a box pointing at nothing');
    }

    /** A required attribute is removed with two clicks in any browser. */
    public function testAcceptanceIsEnforcedOnTheServer(): void
    {
        $body = $this->checkout();
        $this->assertStringContainsString(
            "if (self::termsPage() !== null && \$request->input('accept_terms') !== '1')",
            $body,
            'the tick box is the only thing standing between an order and the terms'
        );
        $this->assertStringContainsString('Please agree to the terms', $body);

        // And the browser is asked too, so it is caught before a round trip.
        $this->assertMatchesRegularExpression('/name="accept_terms"[^>]*required/', $this->view());
    }

    /** When it happened is the question, so that is what is kept. */
    public function testTheMomentOfAcceptanceIsRecorded(): void
    {
        $this->assertStringContainsString(
            "\$orderData['terms_accepted_at'] = date('Y-m-d H:i:s')",
            $this->checkout()
        );
    }

    /** Reading the page once per request is enough. */
    public function testTheTermsPageIsNotLookedUpRepeatedly(): void
    {
        $fn = substr($this->checkout(), strpos($this->checkout(), 'private static function termsPage('), 600);
        $this->assertStringContainsString('static $page = false;', $fn);
    }

    // ── Shops that have not migrated yet ─────────────────────────────────

    public function testAnOrderIsStillPlaceableBeforeTheMigrationRuns(): void
    {
        $body = $this->checkout();
        $this->assertStringContainsString(
            "unset(\$orderData['idempotency_key'], \$orderData['customer_note'], \$orderData['terms_accepted_at']);",
            $body,
            'a database without these columns would reject every order'
        );
    }
}
