<?php
namespace Core;

/**
 * WHISKER — HTTP Response Helper
 */
class Response
{
    /** Send an HTML response */
    public static function html(string $content, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: text/html; charset=UTF-8');
        echo $content;
        exit;
    }

    /** Send a JSON response */
    public static function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Redirect to URL — strips CR/LF to prevent header injection. */
    public static function redirect(string $url, int $code = 302): void
    {
        // Defense in depth: callers should already be passing trusted URLs
        // (View::url() output, sameOriginRedirect-filtered values, or gateway
        // session URLs from API responses), but a stray \r\n in any of those
        // would let an attacker inject arbitrary response headers via the
        // Location: header. PHP's header() blocks this since 5.1.2, but only
        // when the newline is literal — %0d / %0a in URL-encoded payloads can
        // get through some sanitization paths upstream. Strip both forms here.
        $clean = str_replace(["\r", "\n", "%0d", "%0a", "%0D", "%0A"], '', $url);
        http_response_code($code);
        header('Location: ' . $clean);
        exit;
    }

    /** 404 Not Found */
    public static function notFound(string $message = 'Page not found'): void
    {
        http_response_code(404);
        echo $message; // TODO: render a proper 404 template
        exit;
    }

    /** 403 Forbidden */
    public static function forbidden(string $message = 'Access denied'): void
    {
        http_response_code(403);
        echo $message;
        exit;
    }

    /** Set a response header — strips CR/LF from name and value. */
    public static function header(string $name, string $value): void
    {
        $clean = static fn($s) => str_replace(["\r", "\n", "%0d", "%0a", "%0D", "%0A"], '', $s);
        header($clean($name) . ': ' . $clean($value));
    }
}
