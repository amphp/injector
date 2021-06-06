<?php

namespace Amp\Injector\Provider;

use Amp\Injector\Provider;
use Amp\Injector\ProviderContext;

final class ValueProvider implements Provider
{
    private mixed $value;

    public function __construct(mixed $value)
    {
        $this->value = $value;
    }

    public function get(ProviderContext $context): mixed
    {
        return $this->value;
    }

    public function getDependencies(): array
    {
        return [];
    }

    public function unwrap(): ?Provider
    {
        return null;
    }
}
