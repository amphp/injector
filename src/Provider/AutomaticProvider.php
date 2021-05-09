<?php

namespace Amp\Injector\Provider;

use Amp\Injector\ArgumentRules;
use Amp\Injector\Context;
use Amp\Injector\Executable;
use Amp\Injector\Provider;
use Amp\Injector\InjectionException;
use Amp\Injector\Internal\Reflector;
use Amp\Injector\Internal\StandardReflector;

final class AutomaticProvider implements Provider
{
    private Reflector $reflector;
    private string $class;
    private ArgumentRules $argumentRules;

    public function __construct(string $class, ArgumentRules $argumentRules)
    {
        $this->reflector = new StandardReflector;
        $this->class = $class;
        $this->argumentRules = $argumentRules;
    }

    public function get(Context $context): object
    {
        try {
            $constructor = $this->reflector->getConstructor($this->class);

            if (!$constructor) {
                $object = $this->instantiateWithoutConstructorParameters($this->class);
            } elseif (!$constructor->isPublic()) {
                throw new InjectionException(
                    \sprintf('Cannot instantiate protected/private constructor in class %s', $this->class)
                );
            } elseif ($this->reflector->getConstructorParameters($this->class)) {
                $reflectionClass = $this->reflector->getClass($this->class);
                $executable = new Executable($constructor);
                $argumentRules = $this->argumentRules->resolve($executable);
                $arguments = [];

                foreach($argumentRules as $argumentRule) {
                    $arguments[] = $argumentRule->get($context, $executable);
                }

                $object = $reflectionClass->newInstanceArgs($arguments);
            } else {
                $object = $this->instantiateWithoutConstructorParameters($this->class);
            }

            return $object;
        } catch (\ReflectionException $e) {
            throw new \Exception(
                \sprintf('Could not create %s: %s', $this->class, $e->getMessage()),
                $e
            );
        }
    }

    /**
     * @param class-string $className
     *
     * @return object
     * @throws InjectionException
     */
    private function instantiateWithoutConstructorParameters(string $className): object
    {
        $class = $this->reflector->getClass($className);

        if (!$class->isInstantiable()) {
            throw new \Exception(\sprintf(
                'Injection definition required for %s %s',
                $class->isInterface() ? 'interface' : 'abstract class',
                $className
            ));
        }

        return new $className;
    }
}