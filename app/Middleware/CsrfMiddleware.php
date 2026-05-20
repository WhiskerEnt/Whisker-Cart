<?php
namespace App\Middleware;

use Core\Request;
use Core\Response;
use Core\Session;

/**
 * Verifies CSRF token on all POST requests.
 * Token can come from:
 *   - Form field: wk_csrf (multipart/urlencoded POST)
 *   - HTTP header: X-CSRF-Token (AJAX / fetch)
 *   - JSON body: wk_csrf inside application/json body
 */
class CsrfMiddleware
{
    public function handle(Request $request): bool
    {
        if (!$request->isPost()) return true;

        // Get token from form field, header, or JSON body. M20: fetch() calls
        // that send Content-Type: application/json don't populate $_POST, so
        // we need to look inside the parsed JSON for wk_csrf as well.
        $token = $request->input('wk_csrf')
              ?? $request->server('HTTP_X_CSRF_TOKEN');

        if (!$token) {
            $contentType = (string)$request->server('CONTENT_TYPE', '');
            if (str_contains($contentType, 'application/json')) {
                $json = $request->json();
                if (is_array($json) && isset($json['wk_csrf'])) {
                    $token = (string)$json['wk_csrf'];
                }
            }
        }

        if (!$token || !Session::verifyCsrf($token)) {
            // Detect AJAX/fetch
            $isAjax = $request->isAjax()
                || str_contains((string)$request->server('HTTP_ACCEPT', ''), 'application/json')
                || str_contains((string)$request->server('CONTENT_TYPE', ''), 'multipart/form-data')
                || str_contains((string)$request->server('CONTENT_TYPE', ''), 'application/json');

            if ($isAjax) {
                Response::json(['success' => false, 'error' => 'CSRF token invalid or expired. Reload the page.'], 403);
                return false;
            }

            Session::flash('error', 'Your session expired. Please try again.');
            // M2: HTTP_REFERER is attacker-controlled. Following it blindly
            // gives them an open-redirect primitive — `<form action="/some-post-endpoint"
            // method="POST">` with Referer set to `https://evil.com` would,
            // on CSRF failure, redirect the victim off-site for phishing.
            // Only honor Referer when it points back to our own origin.
            $referer = (string)$request->server('HTTP_REFERER', '');
            Response::redirect(self::sameOriginRedirect($referer));
            return false;
        }

        return true;
    }

    /**
     * Return $referer if it points at the current host, else '/'.
     */
    private static function sameOriginRedirect(string $referer): string
    {
        if ($referer === '') return '/';
        $refHost = parse_url($referer, PHP_URL_HOST);
        if (!$refHost) return '/';
        $ownHost = $_SERVER['HTTP_HOST'] ?? '';
        // Trim possible port suffix from HTTP_HOST for comparison
        $ownHostOnly = explode(':', $ownHost, 2)[0];
        if (strcasecmp($refHost, $ownHost) === 0
            || strcasecmp($refHost, $ownHostOnly) === 0) {
            return $referer;
        }
        return '/';
    }
}