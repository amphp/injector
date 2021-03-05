<?php

namespace Amp\Injector;

interface Reflector
{
    /**
     * Retrieves ReflectionClass instances, caching them for future retrieval.
     *
     * @param class-string $class
     *
     * @return \ReflectionClass
     */
    public function getClass(string $class): \ReflectionClass;

    /**
     * Retrieves and caches the constructor (ReflectionMethod) for the specified class.
     *
     * @param class-string $class
     *
     * @return \ReflectionMethod|null
     */
    public function getCtor(string $class): ?\ReflectionMethod;

    /**
     * Retrieves and caches an array of constructor parameters for the given class.
     *
     * @param class-string $class
     *
     * @return \ReflectionParameter[]|null
     */
    public function getCtorParams(string $class): ?array;

    /**
     * Retrieves the class type-hint from a given ReflectionParameter.
     *
     * There is no way to directly access a parameter's type-hint without
     * instantiating a new ReflectionClass instance and calling its getName()
     * method. This method stores the results of this approach so that if
     * the same parameter type-hint or ReflectionClass is needed again we
     * already have it cached.
     *
     * @param \ReflectionFunctionAbstract $function
     * @param \ReflectionParameter        $param
     *
     * @return string|null
     */
    public function getParamTypeHint(\ReflectionFunctionAbstract $function, \ReflectionParameter $param): ?string;

    /**
     * Retrieves and caches a reflection for the specified function.
     *
     * @param string $functionName
     *
     * @return \ReflectionFunction
     */
    public function getFunction(string $functionName): \ReflectionFunction;

    /**
     * Retrieves and caches a reflection for the specified class method.
     *
     * @param object|class-string $classNameOrInstance
     * @param string $methodName
     *
     * @return \ReflectionMethod
     */
    public function getMethod(object|string $classNameOrInstance, string $methodName): \ReflectionMethod;
}
