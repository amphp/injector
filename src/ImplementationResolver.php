<?php

namespace Amp\Injector;

interface ImplementationResolver
{
    public function get(string $class): ?string;
}