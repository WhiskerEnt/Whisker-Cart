<?php
namespace Tests\Unit;

use App\Services\CancellationService;
use PHPUnit\Framework\TestCase;

/**
 * Cancelling touches stock, customer totals and money, from two directions.
 * The rules about what each one may do are asserted here, because getting
 * them wrong either loses inventory or keeps a customer's money.
 */
class CancellationTest extends TestCase
{
    private function service(): string
    {
        return (string) file_get_contents(WK_ROOT . '/app/Services/CancellationService.php');
    }

    public function testACustomerMayOnlyCancelBeforeItShips(): void
    {
        foreach (['pending', 'processing'] as $status) {
            $this->assertTrue(
                CancellationService::customerCanCancel(['status' => $status]),
                "a customer should be able to cancel a {$status} order"
            );
        }
        foreach (['shipped', 'delivered', 'cancelled', 'refunded', 'paid'] as $status) {
            $this->assertFalse(
                CancellationService::customerCanCancel(['status' => $status]),
                "a customer must not be able to cancel a {$status} order"
            );
        }
    }

    /** The button and the endpoint must agree, or the page offers a dead action. */
    public function testTheCancelButtonUsesTheSameRuleAsTheServer(): void
    {
        $view = (string) file_get_contents(WK_ROOT . '/views/store/account/order-detail.php');
        $this->assertStringContainsString(
            'CancellationService::customerCanCancel',
            $view,
            'the cancel button must be shown by the same rule the server enforces'
        );
    }

    public function testNoWindowIsSetByDefault(): void
    {
        $this->assertStringContainsString(
            "Database::setting('checkout', 'cancel_window_minutes', '0')",
            $this->service(),
            'zero means no time limit, which is what an existing shop already had'
        );

        $migration = (string) file_get_contents(WK_ROOT . '/sql/migrations/20260822_v141_cancel_refund.sql');
        $this->assertStringContainsString("('checkout', 'cancel_window_minutes', '0')", $migration);
    }

    /** An order with no readable date must not be locked out by the window. */
    public function testAMissingOrderDateLeavesTheWindowOpen(): void
    {
        $this->assertTrue(\App\Services\CancellationService::withinCancelWindow([]));
        $this->assertTrue(\App\Services\CancellationService::withinCancelWindow(['created_at' => 'nonsense']));
        $this->assertNull(\App\Services\CancellationService::cancelDeadline(['created_at' => 'nonsense']));
    }

    /** With no window configured there is no deadline to show. */
    public function testNoDeadlineWhenThereIsNoLimit(): void
    {
        $this->assertNull(
            \App\Services\CancellationService::cancelDeadline(['created_at' => '2026-08-26 10:00:00']),
            'a shop with no window set has nothing to count down to'
        );
    }

    /** The window is enforced on the way in, not only by hiding the button. */
    public function testTheWindowIsEnforcedServerSide(): void
    {
        $src = $this->service();
        $cancel = substr($src, strpos($src, 'public static function cancel('));
        $cancel = substr($cancel, 0, strpos($cancel, 'private static function everythingButCancelled('));

        $this->assertStringContainsString('self::withinCancelWindow($order)', $cancel);

        $checkAt = strpos($cancel, 'self::withinCancelWindow($order)');
        $writeAt = strpos($cancel, "UPDATE wk_orders SET status='cancelled'");
        $this->assertLessThan($writeAt, $checkAt, 'the window must be checked before the status is written');
    }

    /** A shopkeeper is not bound by the customer-facing window. */
    public function testTheWindowAppliesToCustomersOnly(): void
    {
        $this->assertMatchesRegularExpression(
            '/if \(\$customer && !self::withinCancelWindow\(\$order\)\)/',
            $this->service(),
            'an admin cancelling on the customer behalf is a decision, not a self-service action'
        );
    }

    /** Goods already with a carrier are not back on the shelf. */
    public function testStockOnlyReturnsForOrdersThatNeverShipped(): void
    {
        $src = $this->service();
        $this->assertMatchesRegularExpression(
            "/STOCK_RETURNS_FROM = \[[^\]]*'pending'[^\]]*'processing'[^\]]*'paid'/s",
            $src
        );
        preg_match("/STOCK_RETURNS_FROM = \[(.*?)\]/s", $src, $m);
        $this->assertStringNotContainsString("'shipped'", $m[1], 'a shipped order must not put stock back');
        $this->assertStringNotContainsString("'delivered'", $m[1], 'a delivered order must not put stock back');
    }

    /** Two requests racing must not restore stock twice. */
    public function testTheStatusChangeIsAtomic(): void
    {
        $src = $this->service();
        $this->assertMatchesRegularExpression(
            "/UPDATE wk_orders SET status='cancelled' WHERE id=\? AND status IN/",
            $src,
            'the status must be gated inside the UPDATE, not checked beforehand'
        );

        // Nothing may run when the row did not change.
        $cancel = substr($src, strpos($src, 'public static function cancel('));
        $guardAt = strpos($cancel, 'if ($changed === 0)');
        $stockAt = strpos($cancel, 'self::restoreStock(');
        $this->assertNotFalse($guardAt);
        $this->assertLessThan($stockAt, $guardAt, 'the no-op guard must come before stock is touched');
    }

    /**
     * Cancelling an order that is already refunded would write over that
     * status and take the customer's order count down a second time for a
     * cancellation that already happened.
     */
    public function testARefundedOrderCannotBeCancelledAgain(): void
    {
        $src = $this->service();
        preg_match('/function everythingButCancelled\(\): array\s*\{\s*return \[(.*?)\];/s', $src, $m);
        $this->assertNotEmpty($m, 'could not read the admin-cancellable list');
        $this->assertStringNotContainsString("'refunded'", $m[1], 'a refunded order is at the end of the road');
        $this->assertStringNotContainsString("'cancelled'", $m[1], 'so is a cancelled one');

        $this->assertStringContainsString(
            'This order has already been refunded.',
            $src,
            'and it should say so, rather than claiming it is already cancelled'
        );
    }

    public function testAutoRefundIsOffUntilTheShopAsksForIt(): void
    {
        $this->assertStringContainsString(
            "Database::setting('checkout', 'auto_refund_on_cancel', '0')",
            $this->service(),
            'the default must be off — this moves money without anyone looking'
        );

        $migration = (string) file_get_contents(WK_ROOT . '/sql/migrations/20260822_v141_cancel_refund.sql');
        $this->assertStringContainsString("('checkout', 'auto_refund_on_cancel', '0')", $migration);
    }

    public function testOnlyPaidOrdersAreRefunded(): void
    {
        $src = $this->service();
        $refund = substr($src, strpos($src, 'private static function refundIfAsked('));
        $refund = substr($refund, 0, strpos($refund, 'private static function describe('));

        $this->assertStringContainsString('self::autoRefundEnabled()', $refund);
        $this->assertStringContainsString("self::PAID, true", $refund, 'an unpaid order has nothing to refund');
        $this->assertStringContainsString('refundableAmount', $refund, 'never refund more than is outstanding');
    }

    /** A gateway that refuses must not leave a half-cancelled order. */
    public function testARefundFailureDoesNotFailTheCancellation(): void
    {
        $src = $this->service();
        $cancel = substr($src, strpos($src, 'public static function cancel('));
        $cancel = substr($cancel, 0, strpos($cancel, 'private static function everythingButCancelled('));

        $refundAt = strpos($cancel, 'self::refundIfAsked(');
        $returnAt = strpos($cancel, "'success'        => true,");
        $this->assertNotFalse($refundAt);
        $this->assertNotFalse($returnAt);
        $this->assertLessThan($returnAt, $refundAt, 'the refund is attempted before the success result is built');

        $this->assertMatchesRegularExpression(
            '/catch \(\\\\Throwable \$e\)/',
            $src,
            'a refund that throws must be caught, not abort the cancellation'
        );
    }

    /** Both entry points must go through the service, or they drift apart. */
    public function testBothCancelPathsUseTheService(): void
    {
        $customer = (string) file_get_contents(WK_ROOT . '/app/Controllers/Store/AccountController.php');
        $this->assertStringContainsString('CancellationService::cancel($order, null, true)', $customer);

        $admin = (string) file_get_contents(WK_ROOT . '/app/Controllers/Admin/OrderController.php');
        $this->assertStringContainsString('CancellationService::cancel($existing', $admin);

        // The old hand-rolled stock restore must be gone from the customer path.
        $this->assertSame(
            0,
            preg_match('/UPDATE wk_products SET stock_quantity = stock_quantity \+ \?/', $customer),
            'stock handling must live in the service, not be duplicated in the controller'
        );
    }

    /** Cancelling from the admin is a real cancellation, not just a label change. */
    public function testAdminCancelDoesMoreThanWriteAStatus(): void
    {
        $admin = (string) file_get_contents(WK_ROOT . '/app/Controllers/Admin/OrderController.php');
        $updateStatus = substr($admin, strpos($admin, 'public function updateStatus('));
        $updateStatus = substr($updateStatus, 0, 3000);

        $this->assertMatchesRegularExpression(
            "/if \(\\\$status === 'cancelled'\)/",
            $updateStatus,
            'cancelling from the status dropdown must be handled, not written as a plain status'
        );
    }

    public function testTheSettingIsSaveable(): void
    {
        $controller = (string) file_get_contents(WK_ROOT . '/app/Controllers/Admin/SettingsController.php');
        $this->assertStringContainsString('auto_refund_on_cancel', $controller, 'the field must be in the save whitelist');

        $view = (string) file_get_contents(WK_ROOT . '/views/admin/settings.php');
        $this->assertStringContainsString('name="checkout_auto_refund_on_cancel"', $view);
    }
}
