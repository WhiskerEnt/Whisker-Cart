<?php
namespace App\Controllers\Store;

use App\Services\QuestionService;
use Core\{Request, View, Database, Response, Session, RateLimiter};

class QuestionController
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW = 900;

    public function store(Request $request, array $params = []): void
    {
        $slug = (string) $request->input('product_slug');
        $back = View::url('product/' . $slug) . '#questions';

        if (!QuestionService::enabled()) {
            Session::flash('error', 'Questions are turned off for this store.');
            Response::redirect($back);
            return;
        }

        if (!RateLimiter::attempt('question_ask', $request->ip(), self::MAX_ATTEMPTS, self::WINDOW)) {
            $wait = max(1, (int) ceil(RateLimiter::remainingSeconds('question_ask', $request->ip(), self::WINDOW) / 60));
            Session::flash('error', "Too many questions sent from here. Try again in {$wait} minute" . ($wait === 1 ? '' : 's') . '.');
            Response::redirect($back);
            return;
        }

        $product = Database::fetch("SELECT id FROM wk_products WHERE slug=? AND is_active=1", [$slug]);
        if (!$product) { Response::notFound(); return; }

        $result = QuestionService::ask((int) $product['id'], [
            'name'     => $request->input('name'),
            'email'    => $request->input('email'),
            'question' => $request->input('question'),
        ], Session::customerId());

        if ($result['success']) {
            RateLimiter::reset('question_ask', $request->ip());
        }

        Session::flash($result['success'] ? 'success' : 'error', $result['message']);
        Response::redirect($back);
    }
}
