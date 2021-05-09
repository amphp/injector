<?php

namespace Amp\Injector\TypeRules;

use Amp\Injector\TypeRules;
use Amp\Injector\InjectionException;
use Amp\Injector\Internal\Reflector;
use function Amp\Injector\Internal\normalizeClass;

// TODO: Build precompiled version with this registry as fallback
// TODO: Make immutable?
final class AutomaticTypeRules implements TypeRules
{
    /** @var string[][] */
    private array $identifiers = [];

    private Reflector $reflector;

    public function __construct(Reflector $reflector)
    {
        $this->reflector = $reflector;
    }

    public function get(string $class): ?string
    {
        $candidates = $this->identifiers[normalizeClass($class)] ?? [];
        $count = \count($candidates);

        if ($count === 0) {
            return null;
        }

        if ($count > 1) {
            throw new InjectionException('Conflict: Multiple implementations found for ' . normalizeClass($class));
        }

        return \reset($candidates);
    }

    public function add(string $class, string $id): void
    {
        $this->registerParents($id, $this->reflector->getClass($class));
    }

    private function registerParents(string $id, \ReflectionClass $class): void
    {
        $this->identifiers[normalizeClass($class->getName())][$id] = $id;

        $parent = $class->getParentClass();
        if ($parent) {
            $this->registerParents($id, $parent);
        }

        foreach ($class->getInterfaces() as $interface) {
            $this->registerParents($id, $interface);
        }
    }
}