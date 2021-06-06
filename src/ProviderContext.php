<?php

namespace Amp\Injector;

use Amp\Injector\Meta\Parameter;

final class ProviderContext
{
    private array $parameters = [];

    public function withParameter(Parameter $parameter): self
    {
        $clone = clone $this;
        $clone->parameters[] = $parameter;

        return $clone;
    }

    public function getParameter(int $offset = 0): ?Parameter
    {
        return $this->parameters[\count($this->parameters) - $offset - 1] ?? null;
    }
}
