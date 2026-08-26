<?php
namespace App\Controllers\Store;

use App\Services\LeadService;
use Core\{Request, Response, Session, RateLimiter};

class LeadController
{
    public function capture(Request $request, array $params = []): void
    {
        if (!LeadService::enabled()) {
            Response::json(['success' => false, 'message' => 'Not available.'], 404);
            return;
        }

        if (!Session::verifyCsrf($request->input('wk_csrf') ?? $request->server('HTTP_X_CSRF_TOKEN'))) {
            Response::json(['success' => false, 'message' => 'Session expired. Please reload the page.'], 403);
            return;
        }

        if (!RateLimiter::attempt('lead_capture', $request->ip(), 8, 900)) {
            Response::json(['success' => false, 'message' => 'Too many attempts. Please try again later.'], 429);
            return;
        }

        $result = LeadService::capture(
            (string) $request->input('email'),
            (string) $request->input('phone'),
            LeadService::currentCartId()
        );

        Response::json($result, $result['success'] ? 200 : 422);
    }
}
