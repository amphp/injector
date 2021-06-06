<?php

namespace Amp\Injector\Definition;

use Amp\Injector\Definition;
use Amp\Injector\Injector;
use Amp\Injector\Meta\Type;
use Amp\Injector\Provider;

final class SingletonDefinition implements Definition
{
    private \WeakMap $instances;
    private Definition $definition;

    public function __construct(Definition $definition)
    {
        $this->definition = $definition;
        $this->instances = new \WeakMap;
    }

    public function getType(): ?Type
    {
        return $this->definition->getType();
    }

    public function getAttribute(string $attribute): ?object
    {
        return $this->definition->getAttribute($attribute);
    }

    public function build(Injector $injector): Provider
    {
        return $this->instances[$injector] ??= new Provider\SingletonProvider($this->definition->build($injector));
    }
}
