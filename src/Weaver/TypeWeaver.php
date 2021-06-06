<?php

namespace Amp\Injector\Weaver;

use Amp\Injector\Definition;
use Amp\Injector\Internal\Reflector;
use Amp\Injector\Meta\Parameter;
use Amp\Injector\Weaver;
use function Amp\Injector\Internal\getDefaultReflector;
use function Amp\Injector\Internal\normalizeClass;

final class TypeWeaver implements Weaver
{
    private Reflector $reflector;

    /** @var Definition[][] */
    private array $implicit = [];

    /** @var Definition[] */
    private array $explicit = [];

    public function __construct()
    {
        $this->reflector = getDefaultReflector();
    }

    public function with(string $class, Definition $definition): self
    {
        $clone = clone $this;

        $normalizedName = normalizeClass($class);
        $clone->explicit[$normalizedName] = $definition;

        foreach ($clone->reflector->getParents($class) as $parent) {
            $clone->implicit[normalizeClass($parent)][$normalizedName] = $definition;
        }

        return $clone;
    }

    public function getDefinition(Parameter $parameter): ?Definition
    {
        if ($type = $parameter->getType()) {
            foreach ($type->getTypes() as $type) {
                $key = normalizeClass($type);
                if (isset($this->explicit[$key])) {
                    return $this->explicit[$key];
                }

                if (isset($this->implicit[$key]) && \count($this->implicit[$key]) === 1) {
                    return $this->implicit[$key][\array_key_first($this->implicit[$key])];
                }
            }
        }

        return null;
    }
}
