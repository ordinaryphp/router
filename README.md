# ordinary/router

A fast PHP 8.5 HTTP router with a global parameter registry, named routes, URL generation, and attribute-based route discovery.

## Key design principles

- **Global parameter registry** — constraints are declared once (`bookId` is always an integer ≥ 1) and reused across every route that references `{bookId}`. No inline constraint syntax.
- **Full path declaration** — routes are one string, no groups or prefix chains.
- **Post-match validation** — params are range-checked after the regex matches. A `MatchStatus::Found` result guarantees all params are valid.
- **Compile once, dispatch many** — safe for long-running processes (Swoole, RoadRunner, Amp). Route data can be cached to disk or a PSR-6/PSR-16 pool.
- **Named routes + URL generation** — `compile()` returns an object that implements both `DispatcherInterface` and `UrlGeneratorInterface`.
- **No magic** — zero autowiring, no hidden convention scanning. Every wire is traceable by reading the code.

## Installation

```bash
composer require ordinary/router
```

Requires PHP ≥ 8.5 and `psr/http-message: ^2.0`.

Optional cache adapters:

```bash
composer require psr/simple-cache  # PSR-16 via Psr16Cache
composer require psr/cache         # PSR-6 via Psr6Cache
```

## Quick start

```php
use Ordinary\Router\Router;
use Ordinary\Router\MatchStatus;
use Ordinary\Router\TrailingSlashMode;

$router = new Router();

// 1. Register parameters first
$router->param('bookId')->integer(min: 1);
$router->param('status')->enum(BookStatus::class);

// 2. Register routes
$router->get('/books',              'BookController::index',  name: 'book.index');
$router->get('/books/{bookId}',     'BookController::show',   name: 'book.show');
$router->post('/books',             'BookController::create', name: 'book.create');
$router->get('/books/by-status/{status}', 'BookController::byStatus');

// 3. Compile once
$dispatcher = $router->compile();

// 4. Dispatch
$result = $dispatcher->dispatch('GET', '/books/42');

match ($result->status) {
    MatchStatus::Found           => callHandler($result->handler, $result->params),
    MatchStatus::NotFound        => send404(),
    MatchStatus::MethodNotAllowed => send405($result->allowedMethods),
    MatchStatus::RedirectRequired => redirect($result->redirectTo),
};

// 5. Generate URLs
echo $dispatcher->generate('book.show', ['bookId' => 42]); // /books/42
```

## Router constructor

```php
new Router(
    trailingSlash: TrailingSlashMode::Strict,  // default
    autoHeadFromGet: true,                     // default: HEAD mirrors GET
)
```

### `TrailingSlashMode`

| Case | Behaviour |
|------|-----------|
| `Strict` | `/foo` and `/foo/` are distinct routes |
| `Ignore` | trailing slash stripped silently before matching |
| `Redirect` | trailing slash returns `MatchStatus::RedirectRequired` pointing to the canonical (slash-free) URL; root `/` is never redirected |

## Registering parameters

Call `$router->param(name)` and chain exactly one terminal method before registering any route that uses `{name}`.

```php
// Built-in constraint types
$router->param('id')->integer(min: 1);                   // digits, optional range
$router->param('page')->range(min: 1, max: 100);         // positive integer with explicit bounds
$router->param('status')->enum(OrderStatus::class);      // backed enum — alternation of case values
$router->param('slug')->slug();                          // [a-z0-9][a-z0-9-]*
$router->param('token')->uuid();                         // UUID v4 (case-insensitive)
$router->param('word')->alpha();                         // [a-zA-Z]+
$router->param('code')->alphanumeric();                  // [a-zA-Z0-9]+
$router->param('anything')->any();                       // [^/]+ (any single segment)
$router->param('hex')->pattern('[0-9a-f]+');             // custom PCRE fragment
$router->param('filePath')->wildcard();                  // .+ — spans path separators, must be last
```

### Range validation

`integer()` and `range()` store range bounds separately from the regex. After the regex matches, the dispatcher checks the integer value against the bounds. A value outside the range is treated as no-match (routing continues to the next candidate).

```php
$router->param('id')->integer(min: 1);

// /items/0   → MatchStatus::NotFound  (below minimum)
// /items/abc → MatchStatus::NotFound  (regex fails)
// /items/42  → MatchStatus::Found
```

### Wildcard parameters

A wildcard param uses the pattern `.+`, which matches across path separators. It **must** be the last segment in the route path.

```php
$router->param('filePath')->wildcard();
$router->get('/uploads/{filePath}', 'FileController::serve');

// /uploads/2024/january/cover.jpg → params: ['filePath' => '2024/january/cover.jpg']
```

## Registering routes

```php
// Per-method convenience methods
$router->get(string $path, mixed $handler, ?string $name = null): void
$router->post(string $path, mixed $handler, ?string $name = null): void
$router->put(string $path, mixed $handler, ?string $name = null): void
$router->patch(string $path, mixed $handler, ?string $name = null): void
$router->delete(string $path, mixed $handler, ?string $name = null): void
$router->head(string $path, mixed $handler, ?string $name = null): void
$router->options(string $path, mixed $handler, ?string $name = null): void

// Multiple methods at once — accepts HttpMethod enum cases, raw strings, or a mix
$router->map(['GET', 'HEAD', HttpMethod::Post], '/resource', 'handler', name: 'resource');
```

`$handler` is `mixed` — the router stores it as-is and returns it in `MatchResult::$handler`. Pass a controller string, an array `[MyController::class, 'action']`, a closure, or whatever your framework resolves.

When `autoHeadFromGet` is `true` (the default), registering a `GET` route automatically adds `HEAD` for the same path and handler.

## Compiling and dispatching

```php
$dispatcher = $router->compile();

// Dispatch by method string + path string
$result = $dispatcher->dispatch('GET', '/books/42');

// Or from a PSR-7 request
$result = $dispatcher->dispatchRequest($psrRequest);
```

### `MatchResultInterface` properties

| Property | Type | Populated when |
|----------|------|----------------|
| `$status` | `MatchStatus` | always |
| `$handler` | `mixed` | `Found` |
| `$params` | `array<string, string>` | `Found` |
| `$allowedMethods` | `list<string>` | `MethodNotAllowed` |
| `$redirectTo` | `?string` | `RedirectRequired` |

Always check `$status` before accessing other properties.

## URL generation

```php
$dispatcher = $router->compile();

// Static route
$dispatcher->generate('book.index');                           // /books

// Dynamic route — pass param values as string or int
$dispatcher->generate('book.show', ['bookId' => 42]);         // /books/42

// Check existence
$dispatcher->has('book.show');   // true
$dispatcher->has('no.such');     // false
```

`generate()` validates every substituted value against its registered constraint and throws `InvalidArgumentException` on failure.

## Caching

Compile once, store the compiled route data, reuse it across FPM requests or long-lived processes. Handlers are never serialized — only the route structure is cached.

### File cache (opcache-friendly)

```php
use Ordinary\Router\Cache\FileCache;

$cache = new FileCache('/var/cache/routes.php');

$dispatcher = $router->compile(cache: $cache);
// First call: compiles + stores. Subsequent calls: loads from file.

// Invalidate when routes change (e.g. during deployment)
$cache->invalidate();
```

### PSR-16 (SimpleCache)

```php
use Ordinary\Router\Cache\Psr16Cache;

$cache = new Psr16Cache($psrSimpleCachePool, key: 'app_routes');
$dispatcher = $router->compile(cache: $cache);
```

### PSR-6 (CacheItemPool)

```php
use Ordinary\Router\Cache\Psr6Cache;

$cache = new Psr6Cache($psrCachePool, key: 'app_routes');
$dispatcher = $router->compile(cache: $cache);
```

### No-op cache

```php
use Ordinary\Router\Cache\NullCache;

// Useful in long-running processes where compile() runs once at startup
$dispatcher = $router->compile(cache: new NullCache());
```

All cache adapters implement `CacheInterface`:

```php
interface CacheInterface
{
    public function load(): ?CompiledRoutes;
    public function store(CompiledRoutes $routes): void;
    public function invalidate(): void;
}
```

## Attribute-based route discovery

Declare routes directly on invokable action classes or controller methods using PHP attributes.

### `#[RouteClass]` — invokable action class

```php
use Ordinary\Router\Attribute\RouteClass;
use Ordinary\Router\HttpMethod;

#[RouteClass(HttpMethod::Get, '/books/{bookId}', 'book.show')]
final class ShowBookAction
{
    public function __invoke(string $bookId): void { /* ... */ }
}
```

The handler stored in the router is the fully-qualified class name string.

### `#[Route]` — method on a controller

The attribute is repeatable, so one method can handle multiple routes.

```php
use Ordinary\Router\Attribute\Route;
use Ordinary\Router\HttpMethod;

final class BookController
{
    #[Route(HttpMethod::Get, '/books', 'book.index')]
    public function index(): void { /* ... */ }

    #[Route(HttpMethod::Post, '/books', 'book.create')]
    public function create(): void { /* ... */ }
}
```

The handler stored in the router is `[ClassName::class, 'methodName']`.

### `AttributeRouteLoader`

```php
use Ordinary\Router\Attribute\AttributeRouteLoader;

$router = new Router();

// Register params first — attributes don't declare constraints
$router->param('bookId')->integer(min: 1);

$loader = new AttributeRouteLoader($router);

// Load a single class
$loader->loadClass(ShowBookAction::class);

// Load a single method
$loader->loadMethod(BookController::class, 'index');

// Scan a directory recursively (token-parses files — no execution)
$loader->loadDirectory(__DIR__ . '/Actions');

$dispatcher = $router->compile();
```

## Exception handling

Every exception thrown by this package implements `Ordinary\Router\Exception\ExceptionInterface`:

```php
use Ordinary\Router\Exception\ExceptionInterface;
use Ordinary\Router\Exception\InvalidArgumentException;
use Ordinary\Router\Exception\LogicException;

try {
    $router->compile();
} catch (InvalidArgumentException $e) {
    // Unregistered param, invalid regex, non-BackedEnum class, etc.
} catch (LogicException $e) {
    // Duplicate method+path, duplicate route name
} catch (ExceptionInterface $e) {
    // Catch-all for any ordinary/router exception
}
```

## Performance notes

- **Static routes** are stored in a hash map — O(1) lookup regardless of route count.
- **Dynamic routes** are indexed by segment count — a two-segment path only evaluates regexes for two-segment patterns.
- **Wildcard routes** (`.+` params) are tried last, after dynamic routes.
- **Compile once** — call `compile()` at application boot and reuse the returned dispatcher for the entire request or process lifetime. The dispatcher is immutable and safe for concurrent use.
- **Cache the compiled data** — `FileCache` serializes the route table to a PHP file that opcache will cache on the first hit.
