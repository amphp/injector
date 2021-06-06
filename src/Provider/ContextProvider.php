<?php

namespace Amp\Injector\Provider;

use Amp\Injector\Provider;
use Amp\Injector\ProviderContext;

final class ContextProvider implements Provider
{
    public function get(ProviderContext $context): ProviderContext
    {
        return $context;
    }

    public function unwrap(): ?Provider
    {
        return null;
    }

    public function getDependencies(): array
    {
        return [];
    }
}
