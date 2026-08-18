<?php
namespace App\Services;

use Core\Database;
use Core\View;

/**
 * WHISKER — Customer questions about a product
 *
 * A question becomes public only when it has been answered and published
 * together. Publishing without an answer is refused rather than allowed and
 * tidied up later, because a product page showing an unanswered question
 * reads as a shop that does not reply.
 */
class QuestionService
{
    public static function enabled(): bool
    {
        return Database::setting('questions', 'questions_enabled', '1') === '1';
    }

    public static function notifyOnAnswer(): bool
    {
        return Database::setting('questions', 'notify_on_answer', '1') === '1';
    }

    /**
     * Ask a question.
     *
     * @return array{success:bool, message:string}
     */
    public static function ask(int $productId, array $input, ?int $customerId = null): array
    {
        $name     = trim((string) ($input['name'] ?? ''));
        $email    = strtolower(trim((string) ($input['email'] ?? '')));
        $question = trim((string) ($input['question'] ?? ''));

        if (!self::enabled()) {
            return ['success' => false, 'message' => 'Questions are turned off for this store.'];
        }
        if ($name === '' || mb_strlen($name) > 80) {
            return ['success' => false, 'message' => 'Enter your name.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Enter a valid email address.'];
        }
        if (mb_strlen($question) < 8) {
            return ['success' => false, 'message' => 'Please write a little more so we can answer properly.'];
        }
        if (mb_strlen($question) > 1000) {
            return ['success' => false, 'message' => 'Please keep your question under 1000 characters.'];
        }

        Database::insert('wk_questions', [
            'product_id'   => $productId,
            'customer_id'  => $customerId,
            'author_name'  => mb_substr($name, 0, 80),
            'author_email' => mb_substr($email, 0, 190),
            'question'     => $question,
            'status'       => 'pending',
            'ip_address'   => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        return [
            'success' => true,
            'message' => 'Thank you — your question has been sent to the store. We will email you when it is answered.',
        ];
    }

    /** Questions shown under a product: answered and published only. */
    public static function forProduct(int $productId, int $limit = 30): array
    {
        try {
            return Database::fetchAll(
                "SELECT * FROM wk_questions
                  WHERE product_id = ? AND status = 'published' AND answer IS NOT NULL AND answer <> ''
                  ORDER BY answered_at DESC, id DESC
                  LIMIT " . max(1, min(100, $limit)),
                [$productId]
            );
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function countForProduct(int $productId): int
    {
        try {
            return (int) Database::fetchValue(
                "SELECT COUNT(*) FROM wk_questions
                  WHERE product_id = ? AND status = 'published' AND answer IS NOT NULL AND answer <> ''",
                [$productId]
            );
        } catch (\Exception $e) {
            return 0;
        }
    }

    // ── Admin ────────────────────────────────────────────────────────────

    public static function pendingCount(): int
    {
        try {
            return (int) Database::fetchValue("SELECT COUNT(*) FROM wk_questions WHERE status = 'pending'");
        } catch (\Exception $e) {
            return 0;
        }
    }

    /** Save an answer without publishing it, so a draft can be worked on. */
    public static function saveAnswer(int $questionId, string $answer, ?int $adminId): bool
    {
        $answer = trim($answer);
        return Database::update('wk_questions', [
            'answer'      => $answer !== '' ? mb_substr($answer, 0, 4000) : null,
            'answered_by' => $answer !== '' ? $adminId : null,
            'answered_at' => $answer !== '' ? date('Y-m-d H:i:s') : null,
        ], 'id = ?', [$questionId]) >= 0;
    }

    /**
     * Answer and publish in one step.
     *
     * @return array{success:bool, message:string}
     */
    public static function publish(int $questionId, string $answer, ?int $adminId): array
    {
        $answer = trim($answer);
        if ($answer === '') {
            return ['success' => false, 'message' => 'Write an answer before publishing — a question on its own is not much use to anyone.'];
        }

        $q = Database::fetch("SELECT * FROM wk_questions WHERE id = ?", [$questionId]);
        if (!$q) {
            return ['success' => false, 'message' => 'That question no longer exists.'];
        }

        Database::update('wk_questions', [
            'answer'      => mb_substr($answer, 0, 4000),
            'status'      => 'published',
            'answered_by' => $adminId,
            'answered_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$questionId]);

        $emailed = self::notifyAsker($questionId);

        return [
            'success' => true,
            'message' => $emailed
                ? 'Answer published, and the customer has been emailed.'
                : 'Answer published.',
        ];
    }

    public static function reject(int $questionId): bool
    {
        return Database::update('wk_questions', ['status' => 'rejected'], 'id = ?', [$questionId]) > 0;
    }

    public static function unpublish(int $questionId): bool
    {
        return Database::update('wk_questions', ['status' => 'pending'], 'id = ?', [$questionId]) > 0;
    }

    public static function delete(int $questionId): bool
    {
        return Database::delete('wk_questions', 'id = ?', [$questionId]) > 0;
    }

    /**
     * Tell the asker their question has an answer.
     *
     * Sent once. The answer is already published by this point, so a mail
     * failure must not be reported as a failure to publish.
     */
    private static function notifyAsker(int $questionId): bool
    {
        if (!self::notifyOnAnswer()) return false;

        try {
            $q = Database::fetch(
                "SELECT q.*, p.name AS product_name, p.slug AS product_slug
                   FROM wk_questions q
                   LEFT JOIN wk_products p ON p.id = q.product_id
                  WHERE q.id = ?",
                [$questionId]
            );
            if (!$q || $q['notified_at'] || !$q['author_email'] || !$q['answer']) return false;

            $sent = EmailService::sendFromTemplate('question-answered', $q['author_email'], [
                '{{store_name}}'    => Database::setting('general', 'site_name') ?: 'Our store',
                '{{store_url}}'     => View::url(''),
                '{{customer_name}}' => $q['author_name'],
                '{{product_name}}'  => $q['product_name'] ?? 'the product',
                '{{product_url}}'   => View::url('product/' . ($q['product_slug'] ?? '')),
                '{{question}}'      => nl2br(View::e($q['question'])),
                '{{answer}}'        => nl2br(View::e($q['answer'])),
            ]);

            if ($sent) {
                Database::update('wk_questions', ['notified_at' => date('Y-m-d H:i:s')], 'id = ?', [$questionId]);
            }
            return $sent;
        } catch (\Throwable $e) {
            error_log('Whisker: answer notification for question ' . $questionId . ' failed — ' . $e->getMessage());
            return false;
        }
    }
}
