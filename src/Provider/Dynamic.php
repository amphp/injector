<?php

namespace Amp\Injector\Provider;

use Amp\Injector\Context;
use Amp\Injector\Provider;

final class Dynamic implements Provider
{
    private mixed $callable;

    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    public function get(Context $context): mixed
    {
        return ($this->callable)($context);
    }

    public function getType(): ?string
    {
        return null;
    }

    public function getDependencies(Context $context): array
    {
        return [];
    }
}