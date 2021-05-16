<?php

namespace Amp\Injector\Provider;

use Amp\Injector\Context;
use Amp\Injector\Provider;

final class OptionalTypeReference implements Provider
{
    private string $type;
    private Provider $default;

    public function __construct(string $class, Provider $default)
    {
        $this->type = $class;
        $this->default = $default;
    }

    public function get(Context $context): mixed
    {
        if ($context->hasType($this->type)) {
            return $context->getType($this->type);
        }

        return $this->default->get($context);
    }

    public function getType(): ?string
    {
        return null; // We have a type available, but don't want OptionalType to be used for autowiring
    }

    public function getDependencies(Context $context): array
    {
        if ($context->hasType($this->type)) {
            return [$context->getTypeProvider($this->type)];
        } else {
            return [$this->default];
        }
    }
}