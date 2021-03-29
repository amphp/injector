<?php

namespace Amp\Injector;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class ContainerAdapter implements ContainerInterface
{
    protected array $has = [];

    public function __construct(protected Injector $injector)
    {
    }

    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new class('Unable to get: ' . $id) extends \InvalidArgumentException implements NotFoundExceptionInterface {
            };
        }

        try {
            return $this->injector->make($id);
        } catch (\Exception $previous) {
            throw new class('Unable to get: ' . $id, 0, $previous) extends \RuntimeException implements ContainerExceptionInterface {
            };
        }
    }

    public function has(string $id): bool
    {
        static $filter = Injector::I_BINDINGS
            | Injector::I_DELEGATES
            | Injector::I_PREPARES
            | Injector::I_ALIASES
            | Injector::I_SHARES;

        if (isset($this->has[$id])) {
            return $this->has[$id];
        }

        $definitions = \array_filter($this->injector->inspect($id, $filter));
        if (!empty($definitions)) {
            return $this->has[$id] = true;
        }

        if (!\class_exists($id)) {
            return $this->has[$id] = false;
        }

        $reflector = new \ReflectionClass($id);
        if ($reflector->isInstantiable()) {
            return $this->has[$id] = true;
        }

        return $this->has[$id] = false;
    }
}
