<?php

namespace Amp\Injector;

final class CachingReflector implements Reflector
{
    public const CACHE_KEY_CLASSES = 'injector.refls.classes.';
    public const CACHE_KEY_CTORS = 'injector.refls.ctors.';
    public const CACHE_KEY_CTOR_PARAMS = 'injector.refls.ctor-params.';
    public const CACHE_KEY_FUNCS = 'injector.refls.funcs.';
    public const CACHE_KEY_METHODS = 'injector.refls.methods.';

    private Reflector $reflector;
    private ReflectionCache $cache;

    public function __construct(Reflector $reflector = null, ReflectionCache $cache = null)
    {
        $this->reflector = $reflector ?: new StandardReflector;
        $this->cache = $cache ?: new ReflectionCacheArray;
    }

    public function getClass(string $class): \ReflectionClass
    {
        $cacheKey = self::CACHE_KEY_CLASSES . \strtolower($class);

        if (($reflectionClass = $this->cache->fetch($cacheKey)) === false) {
            $this->cache->store($cacheKey, $reflectionClass = $this->reflector->getClass($class));
        }

        return $reflectionClass;
    }

    public function getCtor($class): ?\ReflectionMethod
    {
        $cacheKey = self::CACHE_KEY_CTORS . \strtolower($class);

        if (($reflectedCtor = $this->cache->fetch($cacheKey)) === false) {
            $this->cache->store($cacheKey, $reflectedCtor = $this->reflector->getCtor($class));
        }

        return $reflectedCtor;
    }

    public function getCtorParams($class): ?array
    {
        $cacheKey = self::CACHE_KEY_CTOR_PARAMS . \strtolower($class);

        if (($reflectedCtorParams = $this->cache->fetch($cacheKey)) === false) {
            $this->cache->store($cacheKey, $reflectedCtorParams = $this->reflector->getCtorParams($class));
        }

        return $reflectedCtorParams;
    }

    public function getParamTypeHint(\ReflectionFunctionAbstract $function, \ReflectionParameter $param): ?string
    {
        $lowParam = \strtolower($param->name);

        if ($function instanceof \ReflectionMethod) {
            $lowClass = \strtolower($function->class);
            $lowMethod = \strtolower($function->name);
            $paramCacheKey = self::CACHE_KEY_CLASSES . "{$lowClass}.{$lowMethod}.param-{$lowParam}";
        } else {
            $lowFunc = \strtolower($function->name);
            $paramCacheKey = (\strpos($lowFunc, '{closure}') === false)
                ? self::CACHE_KEY_FUNCS . ".{$lowFunc}.param-{$lowParam}"
                : null;
        }

        $typeHint = ($paramCacheKey === null) ? false : $this->cache->fetch($paramCacheKey);

        if (false === $typeHint) {
            $typeHint = $this->reflector->getParamTypeHint($function, $param);
            if ($paramCacheKey !== null) {
                $this->cache->store($paramCacheKey, $typeHint);
            }
        }

        return $typeHint;
    }

    public function getFunction($functionName): \ReflectionFunction
    {
        $lowFunc = \strtolower($functionName);
        $cacheKey = self::CACHE_KEY_FUNCS . $lowFunc;

        if (($reflectedFunc = $this->cache->fetch($cacheKey)) === false) {
            $this->cache->store($cacheKey, $reflectedFunc = $this->reflector->getFunction($functionName));
        }

        return $reflectedFunc;
    }

    public function getMethod($classNameOrInstance, $methodName): \ReflectionMethod
    {
        $className = \is_string($classNameOrInstance)
            ? $classNameOrInstance
            : \get_class($classNameOrInstance);

        $cacheKey = self::CACHE_KEY_METHODS . \strtolower($className) . '.' . \strtolower($methodName);

        if (($reflectedMethod = $this->cache->fetch($cacheKey)) === false) {
            $this->cache->store($cacheKey, $reflectedMethod = $this->reflector->getMethod($classNameOrInstance, $methodName));
        }

        return $reflectedMethod;
    }
}
