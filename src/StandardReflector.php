<?php

namespace Amp\Injector;

final class StandardReflector implements Reflector
{
    public function getClass(string $class): \ReflectionClass
    {
        return new \ReflectionClass($class);
    }

    public function getCtor(string $class): ?\ReflectionMethod
    {
        $reflectionClass = new \ReflectionClass($class);

        return $reflectionClass->getConstructor();
    }

    public function getCtorParams($class): ?array
    {
        return ($reflectedCtor = $this->getCtor($class))
            ? $reflectedCtor->getParameters()
            : null;
    }

    public function getParamTypeHint(\ReflectionFunctionAbstract $function, \ReflectionParameter $param): ?string
    {
        $type = $param->getType();
        if ($type instanceof \ReflectionNamedType && $type->isBuiltin()) {
            return null;
        }

        return $type ? $type->getName() : null;
    }

    public function getFunction(string $functionName): \ReflectionFunction
    {
        return new \ReflectionFunction($functionName);
    }

    public function getMethod(object|string $classNameOrInstance, string $methodName): \ReflectionMethod
    {
        $className = \is_string($classNameOrInstance) ? $classNameOrInstance : \get_class($classNameOrInstance);

        return new \ReflectionMethod($className, $methodName);
    }
}
