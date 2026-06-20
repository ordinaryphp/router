<?php

declare(strict_types=1);

namespace Ordinary\Router\Attribute;

use Ordinary\Router\Exception\InvalidArgumentException;
use Ordinary\Router\RouterInterface;

/**
 * Discovers and registers routes declared via #[Route] and #[RouteClass] attributes.
 *
 * The handler stored in the router is the FQCN string (for RouteClass) or
 * a [ClassName::class, 'methodName'] array (for Route on a method).
 * The framework is responsible for resolving and invoking the handler.
 */
final readonly class AttributeRouteLoader
{
    public function __construct(private RouterInterface $router) {}

    /**
     * Scan a directory recursively for PHP files and register all routes found via attributes.
     *
     * Class names are extracted via token parsing (no execution) then reflected for attributes.
     * Classes that cannot be autoloaded or have no route attributes are silently skipped.
     *
     * @throws InvalidArgumentException if $directory does not exist or is not readable
     */
    public function loadDirectory(string $directory): void
    {
        if (!\is_dir($directory) || !\is_readable($directory)) {
            throw new InvalidArgumentException(
                \sprintf('Directory "%s" does not exist or is not readable', $directory),
            );
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }

            if ($file->getExtension() !== 'php') {
                continue;
            }

            $realPath = $file->getRealPath();

            if ($realPath === false) {
                continue;
            }

            foreach ($this->extractClassNames($realPath) as $className) {
                try {
                    $this->loadClass($className);
                } catch (\ReflectionException) {
                    // Class could not be reflected — skip silently
                }
            }
        }
    }

    /**
     * Reflect on a single class and register all routes declared via attributes.
     *
     * Checks for #[RouteClass] on the class itself, then #[Route] on each public method.
     *
     * @param class-string $className
     *
     * @throws \ReflectionException if the class cannot be reflected
     */
    public function loadClass(string $className): void
    {
        $rc = new \ReflectionClass($className);

        $this->loadRouteClassAttribute($rc);

        foreach ($rc->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $this->loadMethodRouteAttributes($rc, $method);
        }
    }

    /**
     * Reflect on a single method and register all routes declared via #[Route] attributes.
     *
     * @param class-string $className
     *
     * @throws \ReflectionException if the class or method cannot be reflected
     */
    public function loadMethod(string $className, string $methodName): void
    {
        $rc = new \ReflectionClass($className);
        $rm = $rc->getMethod($methodName);

        $this->loadMethodRouteAttributes($rc, $rm);
    }

    /**
     * @param \ReflectionClass<object> $rc
     */
    private function loadRouteClassAttribute(\ReflectionClass $rc): void
    {
        $attrs = $rc->getAttributes(RouteClass::class);

        if ($attrs === []) {
            return;
        }

        /** @var RouteClass $attr */
        $attr = $attrs[0]->newInstance();

        $this->router->map(
            methods: [$attr->method],
            path: $attr->path,
            handler: $rc->getName(),
            name: $attr->name,
        );
    }

    /**
     * @param \ReflectionClass<object> $rc
     */
    private function loadMethodRouteAttributes(\ReflectionClass $rc, \ReflectionMethod $method): void
    {
        foreach ($method->getAttributes(Route::class) as $attrRef) {
            /** @var Route $attr */
            $attr = $attrRef->newInstance();

            $this->router->map(
                methods: [$attr->method],
                path: $attr->path,
                handler: [$rc->getName(), $method->getName()],
                name: $attr->name,
            );
        }
    }

    /**
     * Parse a PHP file using token_get_all() to extract fully-qualified class names
     * without executing the file.
     *
     * @return list<class-string>
     */
    private function extractClassNames(string $filePath): array
    {
        $source = \file_get_contents($filePath);

        if ($source === false) {
            return [];
        }

        $tokens = \token_get_all($source);
        $classNames = [];
        $namespace = '';
        $count = \count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (!\is_array($tokens[$i])) {
                continue;
            }

            if ($tokens[$i][0] === T_NAMESPACE) {
                $namespace = $this->readQualifiedName($tokens, $i + 1, $count);
                continue;
            }

            if (\in_array($tokens[$i][0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)
            ) {
                $name = $this->readSimpleName($tokens, $i + 1, $count);

                if ($name !== '') {
                    $fqcn = $namespace !== '' ? $namespace . '\\' . $name : $name;
                    /** @var class-string $fqcn */
                    $classNames[] = $fqcn;
                }
            }
        }

        return $classNames;
    }

    /**
     * Read a namespace or class name from tokens starting at $pos.
     *
     * @param list<array{int, string, int}|string> $tokens
     */
    private function readQualifiedName(array $tokens, int $start, int $count): string
    {
        $name = '';

        for ($i = $start; $i < $count; $i++) {
            if (!\is_array($tokens[$i])) {
                if ($tokens[$i] === ';' || $tokens[$i] === '{') {
                    break;
                }

                continue;
            }

            if (\in_array($tokens[$i][0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_STRING], true)) {
                $name .= $tokens[$i][1];
            } elseif ($tokens[$i][0] === T_NS_SEPARATOR) {
                $name .= '\\';
            } elseif ($tokens[$i][0] === T_WHITESPACE) {
                if ($name !== '') {
                    break;
                }
            }
        }

        return \trim($name, '\\');
    }

    /**
     * Read a simple (unqualified) class name from tokens starting at $pos.
     *
     * @param list<array{int, string, int}|string> $tokens
     */
    private function readSimpleName(array $tokens, int $start, int $count): string
    {
        for ($i = $start; $i < $count; $i++) {
            if (!\is_array($tokens[$i])) {
                continue;
            }

            if ($tokens[$i][0] === T_WHITESPACE) {
                continue;
            }

            if ($tokens[$i][0] === T_STRING) {
                return $tokens[$i][1];
            }

            break;
        }

        return '';
    }
}
