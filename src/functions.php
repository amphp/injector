<?php

namespace Amp\Injector;

use Amp\Injector\Internal\CachingReflector;
use Amp\Injector\Internal\Reflector;
use Amp\Injector\Internal\StandardReflector;
use Amp\Injector\Provider\ObjectProvider;
use Amp\Injector\Provider\Singleton;

function arguments(): Arguments
{
    static $arguments = null;

    if (!$arguments) {
        $arguments = new Arguments;
    }

    return $arguments;
}

function singleton(Provider $provider): Singleton
{
    return new Singleton($provider);
}

/**
 * @throws InjectionException
 */
function autowire(string $class, ?Arguments $arguments = null): ObjectProvider
{
    static $factory = null;

    if (!$factory) {
        $factory = new AutowireFactory(getDefaultReflector());
    }

    return $factory->create($class, $arguments ?? arguments());
}

function getDefaultReflector(): Reflector
{
    static $reflector = null;

    if (!$reflector) {
        $reflector = new CachingReflector(new StandardReflector);
    }

    return $reflector;
}