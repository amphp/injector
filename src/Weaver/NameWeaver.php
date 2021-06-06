<?php

namespace Amp\Injector\Weaver;

use Amp\Injector\Definition;
use Amp\Injector\Meta\Parameter;
use Amp\Injector\Weaver;

final class NameWeaver implements Weaver
{
    private array $names = [];

    public function with(string $name, Definition $definition): self
    {
        $clone = clone $this;
        $clone->names[$name] = $definition;

        return $clone;
    }

    public function getDefinition(Parameter $parameter): ?Definition
    {
        return $this->names[$parameter->getName()] ?? null;
    }
}
