<?php

namespace Amp\Injector\Internal;

/** @internal */
final class StandardReflector implements Reflector
{
    public function getConstructorParameters($className): ?array
    {
        return ($reflectedCtor = $this->getConstructor($className))
            ? $reflectedCtor->getParameters()
            : null;
    }

    /**
     * @throws \ReflectionException
     */
    public function getConstructor(string $className): ?\ReflectionMethod
    {
        $reflectionClass = new \ReflectionClass($className);

        return $reflectionClass->getConstructor();
    }

    public function getParameterType(\ReflectionFunctionAbstract $function, \ReflectionParameter $param): ?string
    {
        $type = $param->getType();
        if ($type instanceof \ReflectionNamedType && $type->isBuiltin()) {
            return null;
        }

        return $type ? (string) $type : null;
    }

    /**
     * @throws \ReflectionException
     */
    public function getFunction(string $functionName): \ReflectionFunction
    {
        return new \ReflectionFunction($functionName);
    }

    /**
     * @throws \ReflectionException
     */
    public function getMethod(object|string $classNameOrInstance, string $methodName): \ReflectionMethod
    {
        $className = \is_string($classNameOrInstance) ? $classNameOrInstance : \get_class($classNameOrInstance);

        return new \ReflectionMethod($className, $methodName);
    }

    public function getParents(string $class): array
    {
        // TODO: Other types
        if (\in_array($class, ['bool', 'int', 'null'], true)) {
            return [];
        }

        $reflectionClass = $this->getClass($class);
        $parents = [];

        $parent = $reflectionClass->getParentClass();
        if ($parent) {
            $parents = \array_merge($parents, [$parent->getName()], $this->getParents($parent->getName()));
        }

        foreach ($reflectionClass->getInterfaces() as $interface) {
            $parents = \array_merge($parents, [$interface->getName()], $this->getParents($interface->getName()));
        }

        return $parents;
    }

    /**
     * @throws \ReflectionException
     */
    public function getClass(string $className): \ReflectionClass
    {
        return new \ReflectionClass($className);
    }
}
