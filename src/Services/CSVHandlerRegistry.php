<?php

declare(strict_types=1);

namespace Nvl\Csv\Services;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Nvl\Csv\Contracts\CSVRowHandler;

/** Retains only stable aliases and class names; handlers resolve in restored context. */
final class CSVHandlerRegistry
{
    /** @var array<string,array{class:class-string<CSVRowHandler>,version:int}> */
    private array $handlers = [];

    public function __construct(private readonly Container $container) {}

    public function register(string $alias, string $handlerClass, int $version = 1): void
    {
        $alias = trim($alias);
        if ($alias === '' || strlen($alias) > 191 || $version < 1 || ! is_a($handlerClass, CSVRowHandler::class, true)) {
            throw new InvalidArgumentException('CSV handlers require a non-empty alias and contract class.');
        }
        if (isset($this->handlers[$alias]) && $this->handlers[$alias] !== ['class' => $handlerClass, 'version' => $version]) {
            throw new InvalidArgumentException("CSV handler alias [{$alias}] is already registered.");
        }
        $this->handlers[$alias] = ['class' => $handlerClass, 'version' => $version];
    }

    public function resolve(string $alias, int $version = 1): CSVRowHandler
    {
        if (! $this->has($alias, $version)) {
            throw new InvalidArgumentException("CSV handler [{$alias}] has no matching immutable version.");
        }
        $class = $this->handlers[$alias]['class'];

        return $this->container->build($class);
    }

    public function has(string $alias, int $version = 1): bool
    {
        return isset($this->handlers[$alias]) && $this->handlers[$alias]['version'] === $version;
    }
}
