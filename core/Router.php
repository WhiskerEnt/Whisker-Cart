<?php
namespace Core;

/**
 * WHISKER — URL Router
 *
 * Supports:
 *   - GET/POST route registration
 *   - Named parameters: /product/{slug}
 *   - Middleware groups
 *   - Route prefixes (for admin, API)
 *
 * Usage:
 *   $router->get('/products', [ProductController::class, 'index']);
 *   $router->post('/cart/add', [CartController::class, 'add']);
 *   $router->group(['prefix' => '/admin', 'middleware' => ['auth']], function($r) {
 *       $r->get('/dashboard', [DashboardController::class, 'index']);
 *   });
 */
class Router
{
    private array $routes     = [];
    private array $middleware = [];
    private string $prefix    = '';

    /** Map of middleware names to classes */
    private array $middlewareMap = [];

    // ── Route Registration ───────────────────────

    public function get(string $path, array|\Closure $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array|\Closure $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function any(string $path, array|\Closure $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    private function addRoute(string $method, string $path, array|\Closure $handler, array $middleware): void
    {
        $fullPath = $this->prefix . $path;
        $allMiddleware = array_merge($this->middleware, $middleware);

        $this->routes[] = [
            'method'     => $method,
            'path'       => $fullPath,
            'pattern'    => $this->buildPattern($fullPath),
            'handler'    => $handler,
            'middleware'  => $allMiddleware,
        ];
    }

    // ── Route Groups ─────────────────────────────

    public function group(array $options, callable $callback): void
    {
        $prevPrefix     = $this->prefix;
        $prevMiddleware = $this->middleware;

        if (isset($options['prefix'])) {
            $this->prefix .= $options['prefix'];
        }
        if (isset($options['middleware'])) {
            $this->middleware = array_merge($this->middleware, $options['middleware']);
        }

        $callback($this);

        $this->prefix     = $prevPrefix;
        $this->middleware  = $prevMiddleware;
    }

    // ── Middleware Registration ───────────────────

    public function registerMiddleware(string $name, string $class): void
    {
        $this->middlewareMap[$name] = $class;
    }

    // ── Dispatch ─────────────────────────────────

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path   = rtrim($request->path(), '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;

            $params = [];
            if (preg_match($route['pattern'], $path, $matches)) {
                // Extract named parameters
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }

                // Run middleware chain
                foreach ($route['middleware'] as $mwName) {
                    if (isset($this->middlewareMap[$mwName])) {
                        $mwClass = $this->middlewareMap[$mwName];
                        $mw = new $mwClass();
                        $result = $mw->handle($request);
                        if ($result === false) return; // Middleware blocked the request
                    }
                }

                // Call the handler. Closures are invoked directly — plugin
                // webhook routes use them because gateway classes live outside
                // the autoloader and need constructor arguments, so the
                // `new $class()` path below can never instantiate them.
                if ($route['handler'] instanceof \Closure) {
                    ($route['handler'])($request, $params);
                    return;
                }
                [$controllerClass, $action] = $route['handler'];
                $controller = new $controllerClass();
                $controller->$action($request, $params);
                return;
            }
        }

        // No route matched
        Response::notFound();
    }

    // ── Pattern Builder ──────────────────────────

    private function buildPattern(string $path): string
    {
        // Convert {param} to named regex groups
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $path);
        // Escape slashes and anchor
        $pattern = '#^' . $pattern . '$#';
        return $pattern;
    }

    /** Get all registered routes (for debugging) */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
