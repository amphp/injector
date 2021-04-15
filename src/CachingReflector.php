<?php

namespace Amp\Injector;

final class CachingReflector implements Reflector
{
    private Reflector $reflector;

    private array $classes = [];
    private array $constructors = [];
    private array $constructorParameters = [];
    private array $methods = [];
    private array $functions = [];
    private array $parameters = [];

    public function __construct(?Reflector $reflector = null)
    {
        $this->reflector = $reflector ?? new StandardReflector;
    }

    public function getClass(string $className): \ReflectionClass
    {
        $key = \strtolower($className);

        return $this->classes[$key] ??= $this->reflector->getClass($className);
    }

    public function getConstructor(string $className): ?\ReflectionMethod
    {
        $key = \strtolower($className);

        return $this->constructors[$key] ??= $this->reflector->getConstructor($className);
    }

    public function getConstructorParameters(string $className): ?array
    {
        $key = \strtolower($className);

        return $this->constructorParameters[$key] ??= $this->reflector->getConstructorParameters($className);
    }

    public function getParameterType(\ReflectionFunctionAbstract $function, \ReflectionParameter $param): ?string
    {
        if ($function instanceof \ReflectionMethod) {
            $lowClass = \strtolower($function->class);
            $lowMethod = \strtolower($function->name);
            $key = "{$lowClass}::{$lowMethod}::{$param->name}";
        } else {
            $lowFunc = \strtolower($function->name);
            $key = "{$lowFunc}::{$param->name}";

            if (\str_contains($lowFunc, '{closure}')) {
                return $this->reflector->getParameterType($function, $param);
            }
        }

        return $this->parameters[$key] ??= $this->reflector->getParameterType($function, $param);
    }

    public function getFunction(string $functionName): \ReflectionFunction
    {
        $key = \strtolower($functionName);

        return $this->functions[$key] ??= $this->reflector->getFunction($functionName);
    }

    public function getMethod(string|object $classNameOrInstance, string $methodName): \ReflectionMethod
    {
        $className = \is_string($classNameOrInstance)
            ? $classNameOrInstance
            : \get_class($classNameOrInstance);

        $key = \strtolower($className) . '::' . \strtolower($methodName);

        return $this->methods[$key] ??= $this->reflector->getMethod($classNameOrInstance, $methodName);
    }
}
