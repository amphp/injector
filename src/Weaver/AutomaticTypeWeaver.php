<?php

namespace Amp\Injector\Weaver;

use Amp\Injector\Definition;
use Amp\Injector\Definitions;
use Amp\Injector\Internal\Reflector;
use Amp\Injector\Meta\Parameter;
use Amp\Injector\Weaver;
use function Amp\Injector\Internal\getDefaultReflector;
use function Amp\Injector\Internal\normalizeClass;

// TODO: Build precompiled version with this registry as fallback
final class AutomaticTypeWeaver implements Weaver
{
    private Reflector $reflector;

    /** @var Definition[][] */
    private array $definitions = [];

    public function __construct(Definitions $definitions)
    {
        $this->reflector = getDefaultReflector();

        foreach ($definitions as $id => $definition) {
            if ($type = $definition->getType()) {
                foreach ($type->getTypes() as $type) {
                    $this->definitions[normalizeClass($type)][$id] = $definition;

                    foreach ($this->reflector->getParents($type) as $parent) {
                        $this->definitions[normalizeClass($parent)][$id] = $definition;
                    }
                }
            }
        }
    }

    public function getDefinition(Parameter $parameter): ?Definition
    {
        if ($type = $parameter->getType()) {
            foreach ($type->getTypes() as $type) {
                $key = normalizeClass($type);
                if (isset($this->definitions[$key])) {
                    $candidates = $this->definitions[$key];
                    if (\count($candidates) === 1) {
                        return \reset($candidates);
                    }
                }
            }
        }

        return null;
    }
}
