<?php
namespace Core;

/**
 * WHISKER — HTTP Request Wrapper
 */
class Request
{
    private string $method;
    private string $uri;
    private string $path;
    private array  $query;
    private array  $body;
    private array  $files;
    private array  $server;
    private array  $cookies;
    private ?array $jsonBody = null;
    private bool   $jsonParsed = false;

    public function __construct()
    {
        $this->method  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->uri     = $_SERVER['REQUEST_URI'] ?? '/';
        $this->query   = $_GET;
        $this->body    = $_POST;
        $this->files   = $_FILES;
        $this->server  = $_SERVER;
        $this->cookies = $_COOKIE;

        // Parse path without query string and base path
        $path = parse_url($this->uri, PHP_URL_PATH) ?: '/';

        // Remove base path if Whisker lives in a subfolder
        if (defined('WK_BASE_PATH') && WK_BASE_PATH !== '/' && WK_BASE_PATH !== '') {
            $basePath = rtrim(WK_BASE_PATH, '/');
            if (str_starts_with($path, $basePath)) {
                $path = substr($path, strlen($basePath));
            }
        }

        // Ensure path starts with / and normalize
        $this->path = '/' . ltrim($path, '/');
    }

    public function method(): string    { return $this->method; }
    public function path(): string      { return $this->path; }
    public function uri(): string       { return $this->uri; }
    public function isPost(): bool      { return $this->method === 'POST'; }
    public function isGet(): bool       { return $this->method === 'GET'; }
    public function isAjax(): bool      { return ($this->server['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'; }
    public function header(string $name): ?string { $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name)); return $this->server[$key] ?? null; }

    /** Get a query parameter (?key=value) */
    public function query(string $key, $default = null)
    {
        return $this->query[$key] ?? $default;
    }

    /** Get a POST body value */
    public function input(string $key, $default = null)
    {
        return $this->body[$key] ?? $default;
    }

    /** Get all POST data */
    public function all(): array
    {
        return $this->body;
    }

    /**
     * Get the JSON request body parsed as an array.
     *
     * Returns null if the body isn't valid JSON (or is empty). Lazy: the
     * body is read from php://input on first call and cached. Used by
     * CsrfMiddleware to pull wk_csrf out of fetch() calls that send
     * Content-Type: application/json (PHP doesn't auto-populate $_POST
     * for those, so $request->input('wk_csrf') returns null otherwise).
     */
    public function json(): ?array
    {
        if (!$this->jsonParsed) {
            $this->jsonParsed = true;
            $raw = file_get_contents('php://input');
            if ($raw !== false && $raw !== '') {
                $decoded = json_decode($raw, true);
                $this->jsonBody = is_array($decoded) ? $decoded : null;
            }
        }
        return $this->jsonBody;
    }

    /** Get uploaded file */
    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    /** Get a server variable */
    public function server(string $key, $default = null)
    {
        return $this->server[$key] ?? $default;
    }

    /** Client IP address — uses REMOTE_ADDR only (X-Forwarded-For is spoofable) */
    public function ip(): string
    {
        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /** Get sanitized input */
    public function clean(string $key, $default = null): ?string
    {
        $val = $this->input($key) ?? $this->query($key) ?? $default;
        return $val !== null ? htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8') : null;
    }

    /**
     * Sanitize an input destined for an HTTP header value (email subject,
     * Reply-To, Content-Disposition filename, redirect path, etc.).
     *
     * htmlspecialchars+trim is fine for HTML output but does NOT strip CR/LF
     * — which is exactly what an attacker uses for header injection: inject
     * "\r\nBcc: attacker@evil" and the entire mail header gets a second
     * recipient. EmailService::send() already strips these on its own header
     * builders; this helper exists so other call sites that route input into
     * a header can be explicit at the point of use rather than relying on a
     * universal strip in clean() that would corrupt legitimate multi-line
     * fields like comment bodies or product descriptions.
     */
    public function cleanHeader(string $key, $default = null): ?string
    {
        $val = $this->clean($key, $default);
        if ($val === null) return null;
        // RFC 5322 / 9112: header values cannot contain CR, LF, or NUL.
        // %0a and %0d are URL-encoded forms that some app stacks decode in
        // unexpected places, so strip those too defensively.
        return str_replace(["\r", "\n", "\0", "%0a", "%0d", "%0A", "%0D"], '', $val);
    }
}
