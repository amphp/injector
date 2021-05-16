<?php

namespace Amp\Injector\ImplementationResolver;

use Amp\Injector\ImplementationResolver;

final class NullImplementationResolver implements ImplementationResolver
{
    public function get(string $class): ?string
    {
        return null;
    }
}