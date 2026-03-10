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
}
