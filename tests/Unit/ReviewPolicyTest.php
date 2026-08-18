<?php
namespace Tests\Unit;

use App\Services\ReviewService;
use PHPUnit\Framework\TestCase;

/**
 * Reviews are public text attached to a product by a stranger, so the rules
 * about who may post and what gets shown are asserted from the source rather
 * than left to review.
 */
class ReviewPolicyTest extends TestCase
{
    private function service(): string
    {
        return (string) file_get_contents(WK_ROOT . '/app/Services/ReviewService.php');
    }

    public function testStarMarkupClipsRatherThanRounding(): void
    {
        // A 4.5 average must render as four and a half stars, not five.
        $html = ReviewService::starMarkup(4.5);
        $this->assertSame(5, substr_count($html, 'wk-star-on'), 'five star slots, each partly filled');
        $this->assertStringContainsString('width:100%', $html);
        $this->assertStringContainsString('width:50%', $html);
        $this->assertStringNotContainsString('width:0%">★', $html);
    }

    public function testStarMarkupHandlesTheEnds(): void
    {
        $none = ReviewService::starMarkup(0.0);
        $this->assertSame(5, substr_count($none, 'width:0%'), 'a zero average fills nothing');

        $full = ReviewService::starMarkup(5.0);
        $this->assertSame(5, substr_count($full, 'width:100%'), 'a five average fills everything');
    }

    public function testPolicyFallsBackToRequiringAPurchase(): void
    {
        $src = $this->service();
        // An unrecognised stored value must not become "anyone can review".
        $this->assertMatchesRegularExpression(
            '/\?\s*\$p\s*:\s*self::POLICY_PURCHASED/',
            $src,
            'an unknown policy value must fall back to requiring a purchase, never to open reviews'
        );
    }

    public function testEligibilityIsCheckedBeforeAnythingIsStored(): void
    {
        $src = $this->service();
        $submit = substr($src, strpos($src, 'public static function submit('));
        $submit = substr($submit, 0, strpos($submit, 'public static function forProduct('));

        $checkAt = strpos($submit, 'self::eligibility(');
        $insertAt = strpos($submit, "Database::insert('wk_reviews'");
        $this->assertNotFalse($checkAt, 'submit() must check eligibility');
        $this->assertNotFalse($insertAt, 'submit() must store the review');
        $this->assertLessThan($insertAt, $checkAt, 'a review that could not be earned must never be stored');
    }

    public function testReceivedPolicyRequiresDelivery(): void
    {
        $this->assertStringContainsString(
            "o.status = 'delivered'",
            $this->service(),
            'the "received it" policy must require a delivered order, not merely a paid one'
        );
    }

    public function testOnlyApprovedReviewsAreEverShown(): void
    {
        $src = $this->service();
        foreach (['forProduct', 'stats', 'statsForMany', 'cardStats'] as $method) {
            $body = substr($src, strpos($src, "function {$method}("));
            $body = substr($body, 0, 1400);
            $this->assertMatchesRegularExpression(
                "/status = 'approved'/",
                $body,
                "{$method}() must only read approved reviews"
            );
        }
    }

    public function testNewReviewsWaitForApprovalByDefault(): void
    {
        $migration = (string) file_get_contents(WK_ROOT . '/sql/migrations/20260818_v140_reviews.sql');
        $this->assertStringContainsString("('reviews', 'auto_approve', '0')", $migration);
        $this->assertStringContainsString("DEFAULT 'pending'", $migration, 'the column itself must default to pending');
    }

    public function testOneReviewPerEmailPerProduct(): void
    {
        $this->assertStringContainsString('hasReviewed', $this->service());
        $eligibility = substr($this->service(), strpos($this->service(), 'function eligibility('));
        $this->assertLessThan(
            strpos($eligibility, 'POLICY_ANYONE'),
            strpos($eligibility, 'self::hasReviewed('),
            'the duplicate check must run whatever the policy is'
        );
    }

    public function testSubmissionIsRateLimitedAndCsrfGated(): void
    {
        $controller = (string) file_get_contents(WK_ROOT . '/app/Controllers/Store/ReviewController.php');
        $this->assertStringContainsString("RateLimiter::attempt('review_post'", $controller);

        $routes = (string) file_get_contents(WK_ROOT . '/config/routes.php');
        $this->assertMatchesRegularExpression(
            "/post\('\/review',.*'csrf'/s",
            $routes,
            'the review endpoint must be POST and CSRF gated'
        );
    }

    public function testReviewTextIsEscapedWhereItIsRendered(): void
    {
        foreach (['views/store/partials/reviews.php', 'views/admin/reviews/index.php'] as $view) {
            $src = (string) file_get_contents(WK_ROOT . '/' . $view);
            // Author-supplied fields must never be echoed raw.
            foreach (['author_name', 'body', 'title'] as $field) {
                $this->assertSame(
                    0,
                    preg_match('/<\?=\s*\$[a-z]+\[\'' . $field . '\'\]/', $src),
                    "{$view}: {$field} is echoed without escaping"
                );
            }
        }
    }

    /**
     * Offline payments are common — bank transfer, cash on collection — and the
     * only record of them is the shopkeeper marking the order paid.
     */
    public function testMarkingAnOrderPaidByHandRecordsThePayment(): void
    {
        $src = (string) file_get_contents(WK_ROOT . '/app/Controllers/Admin/OrderController.php');
        $this->assertMatchesRegularExpression(
            "/payment_status='captured'/",
            $src,
            'marking an order paid, shipped or delivered must record that money arrived, '
            . 'or refunds and review eligibility treat a delivered order as unpaid'
        );
        $this->assertMatchesRegularExpression(
            "/payment_status IN \('pending','authorized'\)/",
            $src,
            'a refunded or failed payment must not be promoted back to captured'
        );
    }

    public function testSchemaOnlyClaimsARatingWhenReviewsExist(): void
    {
        $seo = (string) file_get_contents(WK_ROOT . '/app/Services/SeoService.php');
        $this->assertStringContainsString('aggregateRating', $seo);
        $this->assertStringContainsString('if ($ratingCount > 0)', $seo,
            'publishing aggregateRating with no reviews behind it invites a search penalty');
    }
}
