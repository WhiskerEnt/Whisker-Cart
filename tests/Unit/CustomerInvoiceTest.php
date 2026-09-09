<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The invoice existed, but only the shopkeeper could reach it. In several
 * markets the buyer is entitled to obtain one, and everywhere else asking for
 * it is a support ticket that need not have been raised.
 */
class CustomerInvoiceTest extends TestCase
{
    private function controller(): string
    {
        return (string) file_get_contents(WK_ROOT . '/app/Controllers/Store/AccountController.php');
    }

    private function action(): string
    {
        $src = $this->controller();
        $start = strpos($src, 'public function invoice(Request $request');
        $this->assertNotFalse($start, 'customers still have no way to get an invoice');
        return substr($src, $start, 1100);
    }

    public function testACustomerCanReachTheirOwnInvoice(): void
    {
        $routes = (string) file_get_contents(WK_ROOT . '/config/routes.php');
        $this->assertStringContainsString(
            "\$router->get('/account/order/{id}/invoice', [AccountController::class, 'invoice']);",
            $routes
        );

        $view = (string) file_get_contents(WK_ROOT . '/views/store/account/order-detail.php');
        $this->assertStringContainsString("account/order/' . \$o['id'] . '/invoice", $view,
            'the route exists but nothing links to it');
    }

    /** An id in a URL is trivially changed, so ownership is the whole gate. */
    public function testAnInvoiceIsOnlyServedToWhoeverTheOrderBelongsTo(): void
    {
        $body = $this->action();

        $this->assertMatchesRegularExpression(
            '/WHERE id = \? AND customer_id = \?/',
            $body,
            'the order is fetched without checking who is asking'
        );
        $this->assertStringContainsString('Session::customerId()', $body);
        $this->assertStringContainsString('Response::notFound();', $body,
            'somebody else\'s invoice must not be distinguishable from one that does not exist');
    }

    public function testSignedOutVisitorsAreSentToSignIn(): void
    {
        $body = $this->action();
        $this->assertStringContainsString('if (!Session::customerId())', $body);
        $this->assertStringContainsString("Response::redirect(View::url('account/login'))", $body);
    }

    /** One invoice, rendered one way, for whoever is entitled to see it. */
    public function testItIsTheSameDocumentTheShopkeeperSees(): void
    {
        $this->assertStringContainsString(
            'InvoiceService::generateHTML(',
            $this->action(),
            'a second implementation would drift from the shop\'s real invoice'
        );

        $admin = (string) file_get_contents(WK_ROOT . '/app/Controllers/Admin/OrderController.php');
        $this->assertStringContainsString('InvoiceService::generateHTML(', $admin,
            'the admin side should still be going through the same service');
    }
}
