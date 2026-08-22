<?php
namespace App\Controllers\Store;

use App\Services\ReviewService;
use Core\{Request, View, Database, Response, Session, RateLimiter};

class ReviewController
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW = 900;

    public function store(Request $request, array $params = []): void
    {
        $slug = (string) $request->input('product_slug');
        $back = View::url('product/' . $slug) . '#reviews';

        if (!ReviewService::enabled()) {
            Session::flash('error', 'Reviews are turned off for this store.');
            Response::redirect($back);
            return;
        }

        // Writing is cheap for a bot and expensive for a shopkeeper to clean up.
        if (!RateLimiter::attempt('review_post', $request->ip(), self::MAX_ATTEMPTS, self::WINDOW)) {
            $wait = max(1, (int) ceil(RateLimiter::remainingSeconds('review_post', $request->ip(), self::WINDOW) / 60));
            Session::flash('error', "Too many reviews sent from here. Try again in {$wait} minute" . ($wait === 1 ? '' : 's') . '.');
            Response::redirect($back);
            return;
        }

        $product = Database::fetch("SELECT id FROM wk_products WHERE slug=? AND is_active=1", [$slug]);
        if (!$product) { Response::notFound(); return; }

        $result = ReviewService::submit((int) $product['id'], [
            'name'   => $request->input('name'),
            'email'  => $request->input('email'),
            'rating' => $request->input('rating'),
            'title'  => $request->input('title'),
            'body'   => $request->input('body'),
        ], Session::customerId());

        if ($result['success']) {
            RateLimiter::reset('review_post', $request->ip());
        }

        Session::flash($result['success'] ? 'success' : 'error', $result['message']);
        Response::redirect($back);
    }
}
