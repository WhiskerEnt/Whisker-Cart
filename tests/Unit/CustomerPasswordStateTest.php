<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Whether a customer is asked to set a password, and whether they are then
 * asked to prove the old one, are the same question. They were answered from
 * two different places: the views read a per-customer row in wk_settings that
 * only the set-password and reset flows ever wrote, while the handler read the
 * password_hash sentinel. Registration wrote a real hash and no row, so every
 * registered customer was shown a form with no current-password field, which
 * the handler then rejected for not carrying one.
 */
class CustomerPasswordStateTest extends TestCase
{
    private function controller(): string
    {
        return (string) file_get_contents(WK_ROOT . '/app/Controllers/Store/AccountController.php');
    }

    private function method(string $name): string
    {
        $src = $this->controller();
        $from = strpos($src, 'function ' . $name . '(');
        $this->assertNotFalse($from, "$name() not found");
        $next = strpos($src, "\n    public function ", $from + 1);
        return substr($src, $from, $next === false ? null : $next - $from);
    }

    public function testTheStateIsReadFromTheCustomerRow(): void
    {
        $this->assertStringContainsString(
            "return \$hash !== '' && \$hash !== '*';",
            $this->method('hasPassword'),
            'the sentinel in password_hash is what "has no password yet" means'
        );
    }

    public function testNothingKeepsASecondCopyOfTheAnswer(): void
    {
        foreach (['app', 'views'] as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(WK_ROOT . '/' . $dir));
            foreach ($it as $file) {
                if (!preg_match('/\.php$/', $file->getFilename())) continue;
                $this->assertStringNotContainsString(
                    'customer_flags',
                    (string) file_get_contents((string) $file),
                    basename((string) $file) . ' keeps a second copy of whether a customer has a password'
                );
            }
        }
    }

    public function testBothScreensAskTheSameQuestion(): void
    {
        $this->assertStringContainsString('!self::hasPassword($customer)', $this->method('dashboard'));
        $this->assertStringContainsString('self::hasPassword($customer)', $this->method('profile'));
        $this->assertStringContainsString('if (self::hasPassword($current))', $this->method('setPassword'));
    }

    /**
     * The banner offers a form with no current-password field, so it must
     * only appear where the handler will accept one.
     */
    public function testTheBannerAndTheChangeFormAreMutuallyExclusive(): void
    {
        $view = (string) file_get_contents(WK_ROOT . '/views/store/account/profile.php');

        $this->assertStringContainsString('<?php if (!$hasPassword): ?>', $view, 'the set-password banner is for accounts without one');
        $this->assertStringContainsString('<?php if ($hasPassword): ?>', $view, 'the change-password form is for accounts with one');

        $banner = substr($view, strpos($view, '<?php if (!$hasPassword): ?>'), strpos($view, '<?php if ($hasPassword): ?>') - strpos($view, '<?php if (!$hasPassword): ?>'));
        $this->assertStringNotContainsString('current_password', $banner);

        $change = substr($view, strpos($view, '<?php if ($hasPassword): ?>'));
        $this->assertStringContainsString('name="current_password"', $change);
    }

    public function testRegisteredCustomersAreNotToldTheyCameFromCheckout(): void
    {
        $view = (string) file_get_contents(WK_ROOT . '/views/store/account/profile.php');
        $this->assertStringContainsString('created during checkout', $view);

        $register = $this->method('register');
        $this->assertStringContainsString('password_hash($request->input(\'password\')', $register, 'registration stores a real hash');
        $this->assertStringNotContainsString("'*'", $register, 'registration never stores the no-password sentinel');
    }

    public function testAMissingCustomerCannotSetAPassword(): void
    {
        $set = $this->method('setPassword');
        $this->assertStringContainsString('if (!$current) {', $set, 'a deleted account must not fall through to the no-password branch');
        $this->assertLessThan(
            strpos($set, 'if (self::hasPassword($current))'),
            strpos($set, 'if (!$current) {'),
            'the missing-row check comes first'
        );
    }
}
