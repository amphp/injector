<?php

namespace Amp\Injector\Provider;

use Amp\Injector\Context;
use Amp\Injector\Provider;

final class ValueProvider implements Provider
{
    private mixed $value;

    public function __construct(mixed $value)
    {
        $this->value = $value;
    }

    public function get(Context $context): mixed
    {
        return $this->value;
    }

    public function getDependencies(Context $context): array
    {
        return [];
    }

    public function getType(): ?string
    {
        if (\is_object($this->value)) {
            return \get_class($this->value);
        }

        return null;
    }
}