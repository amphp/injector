<?php

namespace Amp\Injector\Definition;

use Amp\Injector\Arguments;
use Amp\Injector\Definition;
use Amp\Injector\Injector;
use Amp\Injector\Meta\Executable;
use Amp\Injector\Meta\Type;
use Amp\Injector\Provider;

final class FactoryDefinition implements Definition
{
    private Executable $executable;
    private Arguments $arguments;

    public function __construct(Executable $executable, Arguments $arguments)
    {
        $this->executable = $executable;
        $this->arguments = $arguments;
    }

    public function getType(): ?Type
    {
        return $this->executable->getType();
    }

    public function getAttribute(string $attribute): ?object
    {
        return $this->executable->getAttribute($attribute);
    }

    public function build(Injector $injector): Provider
    {
        return $injector->getExecutableProvider($this->executable, $this->arguments);
    }
}
