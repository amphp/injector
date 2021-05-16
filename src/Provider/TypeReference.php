<?php

namespace Amp\Injector\Provider;

use Amp\Injector\Context;
use Amp\Injector\Provider;

final class TypeReference implements Provider
{
    private string $class;

    public function __construct(string $type)
    {
        $this->class = $type;
    }

    public function get(Context $context): object
    {
        return $context->getType($this->class);
    }

    public function getType(): ?string
    {
        return null; // We have a type available, but don't want Type to be used for autowiring
    }

    public function getDependencies(Context $context): array
    {
        return [$context->getTypeProvider($this->class)];
    }
}