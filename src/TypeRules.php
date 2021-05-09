<?php

namespace Amp\Injector;

interface TypeRules
{
    public function get(string $class): ?string;
}