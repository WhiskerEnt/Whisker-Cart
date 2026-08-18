<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The one rule that matters for questions: nothing reaches a product page
 * until it has been answered and published together. Asserted from the source,
 * since a leak here puts an unanswered customer question on a sales page.
 */
class QuestionFlowTest extends TestCase
{
    private function service(): string
    {
        return (string) file_get_contents(WK_ROOT . '/app/Services/QuestionService.php');
    }

    public function testOnlyAnsweredAndPublishedQuestionsAreRead(): void
    {
        $src = $this->service();
        foreach (['forProduct', 'countForProduct'] as $method) {
            $body = substr($src, strpos($src, "function {$method}("));
            $body = substr($body, 0, 900);
            $this->assertMatchesRegularExpression(
                "/status = 'published'/",
                $body,
                "{$method}() must only read published questions"
            );
            $this->assertMatchesRegularExpression(
                "/answer IS NOT NULL AND answer <> ''/",
                $body,
                "{$method}() must also require an answer — published alone could still be blank"
            );
        }
    }

    public function testPublishingWithoutAnAnswerIsRefused(): void
    {
        $src = $this->service();
        $publish = substr($src, strpos($src, 'public static function publish('));
        $publish = substr($publish, 0, strpos($publish, 'public static function reject('));

        // The guard must come before any write.
        $guardAt = strpos($publish, "\$answer === ''");
        $writeAt = strpos($publish, "Database::update('wk_questions'");
        $this->assertNotFalse($guardAt, 'publish() must refuse an empty answer');
        $this->assertNotFalse($writeAt, 'publish() must write the answer');
        $this->assertLessThan($writeAt, $guardAt, 'the empty-answer check must precede the write');
    }

    public function testAskingAlwaysLandsAsPending(): void
    {
        $src = $this->service();
        $ask = substr($src, strpos($src, 'public static function ask('));
        $ask = substr($ask, 0, strpos($ask, 'public static function forProduct('));
        $this->assertMatchesRegularExpression(
            "/'status'\s*=>\s*'pending'/",
            $ask,
            'a customer question must never be stored as anything but pending'
        );
        $this->assertStringNotContainsString("'published'", $ask);
    }

    public function testAskingIsRateLimitedAndCsrfGated(): void
    {
        $controller = (string) file_get_contents(WK_ROOT . '/app/Controllers/Store/QuestionController.php');
        $this->assertStringContainsString("RateLimiter::attempt('question_ask'", $controller);

        $routes = (string) file_get_contents(WK_ROOT . '/config/routes.php');
        $this->assertMatchesRegularExpression(
            "/post\('\/question',.*'csrf'/s",
            $routes,
            'the ask endpoint must be POST and CSRF gated'
        );
    }

    public function testAskerIsEmailedOnceAndOnlyOnce(): void
    {
        $src = $this->service();
        $this->assertStringContainsString('notified_at', $src, 'the notification must be recorded');
        $this->assertMatchesRegularExpression(
            '/\$q\[.notified_at.\]/',
            $src,
            'a second publish must not email the customer again'
        );
    }

    /** Publishing already succeeded by the time the email is attempted. */
    public function testMailFailureDoesNotUndoPublishing(): void
    {
        $src = $this->service();
        $publish = substr($src, strpos($src, 'public static function publish('));
        $publish = substr($publish, 0, strpos($publish, 'public static function reject('));
        $this->assertLessThan(
            strpos($publish, 'self::notifyAsker('),
            strpos($publish, "Database::update('wk_questions'"),
            'the answer must be saved before the email is attempted'
        );
        $this->assertMatchesRegularExpression(
            '/catch \(\\\\Throwable \$e\)/',
            $src,
            'a mail failure must be caught, not bubble out of publishing'
        );
    }

    public function testQuestionTextIsEscapedWhereItIsRendered(): void
    {
        foreach (['views/store/partials/questions.php', 'views/admin/questions/index.php'] as $view) {
            $src = (string) file_get_contents(WK_ROOT . '/' . $view);
            foreach (['question', 'answer', 'author_name', 'author_email'] as $field) {
                $this->assertSame(
                    0,
                    preg_match('/<\?=\s*\$[a-z]+\[\'' . $field . '\'\]/', $src),
                    "{$view}: {$field} is echoed without escaping"
                );
            }
        }
    }

    public function testAnswerEmailIsSeededAsATemplate(): void
    {
        $migration = (string) file_get_contents(WK_ROOT . '/sql/migrations/20260818_v140_questions.sql');
        $this->assertStringContainsString("('question-answered'", $migration);

        $src = $this->service();
        $this->assertStringContainsString("sendFromTemplate('question-answered'", $src);

        // Every placeholder the code supplies must exist in the seeded body,
        // or the customer receives an email with a gap in it.
        preg_match_all("/'\{\{([a-z_]+)\}\}'\s*=>/", $src, $supplied);
        preg_match_all('/\{\{([a-z_]+)\}\}/', $migration, $used);
        foreach (array_unique($used[1]) as $placeholder) {
            $this->assertContains(
                $placeholder,
                $supplied[1],
                "the question-answered template uses {{{$placeholder}}} but nothing supplies it"
            );
        }
    }
}
