<?php

namespace Amp\Injector;

use Amp\Injector\Internal\CachingReflector;
use Amp\Injector\Internal\Reflector;
use Amp\Injector\Internal\StandardReflector;
use Amp\Injector\Provider\ObjectProvider;
use Amp\Injector\Provider\SingletonProvider;
use Amp\Injector\Provider\ValueProvider;

function arguments(): Arguments
{
    static $arguments = null;

    if (!$arguments) {
        $arguments = new Arguments;
    }

    return $arguments;
}

function singleton(Provider $provider): SingletonProvider
{
    return new SingletonProvider($provider);
}

/**
 * @throws InjectionException
 */
function autowire(string $class, ?Arguments $arguments = null): ObjectProvider
{
    static $factory = null;

    if (!$factory) {
        $factory = new AutowireFactory;
    }

    return $factory->create($class, $arguments ?? arguments());
}

function value(mixed $value): ValueProvider
{
    return new ValueProvider($value);
}
