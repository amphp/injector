<?php

namespace Amp\Injector;

use Amp\Injector\Internal\Reflector;
use Amp\Injector\Provider\ObjectProvider;
use function Amp\Injector\Internal\getDefaultReflector;

final class AutowireFactory
{
    private Reflector $reflector;

    public function __construct()
    {
        $this->reflector = getDefaultReflector();
    }

    /**
     * @throws InjectionException
     */
    public function create(string $class, Arguments $arguments): ObjectProvider
    {
        $reflectionClass = $this->reflector->getClass($class);

        $constructor = $this->reflector->getConstructor($class);
        if ($reflectionClass->isInstantiable() && !$constructor) {
            return new ObjectProvider($class, []);
        }

        if (!$constructor->isPublic()) {
            throw new InjectionException('Cannot instantiate protected/private constructor in class ' . $class);
        }

        if (!$reflectionClass->isInstantiable()) {
            throw new InjectionException(\sprintf(
                'Injection definition required for %s %s',
                $reflectionClass->isInterface() ? 'interface' : 'abstract class',
                $class
            ));
        }

        if ($this->reflector->getConstructorParameters($class)) {
            return new ObjectProvider($class, $arguments->resolve(new Executable($constructor)));
        }

        return new ObjectProvider($class, []);
    }
}