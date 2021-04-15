<?php

namespace Amp\Injector\Internal;

/** @internal */
final class StandardReflector implements Reflector
{
    public function getClass(string $className): \ReflectionClass
    {
        return new \ReflectionClass($className);
    }

    public function getConstructor(string $className): ?\ReflectionMethod
    {
        $reflectionClass = new \ReflectionClass($className);

        return $reflectionClass->getConstructor();
    }

    public function getConstructorParameters($className): ?array
    {
        return ($reflectedCtor = $this->getConstructor($className))
            ? $reflectedCtor->getParameters()
            : null;
    }

    public function getParameterType(\ReflectionFunctionAbstract $function, \ReflectionParameter $param): ?string
    {
        $type = $param->getType();
        if ($type instanceof \ReflectionNamedType && $type->isBuiltin()) {
            return null;
        }

        return $type ? (string) $type : null;
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
