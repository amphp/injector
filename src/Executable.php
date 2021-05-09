<?php

namespace Amp\Injector;

final class Executable
{
    private \ReflectionFunctionAbstract $callable;
    private ?object $invocationObject;
    private bool $isInstanceMethod;

    // TODO: Hide reflection?
    public function __construct(\ReflectionFunctionAbstract $callable, ?object $target = null)
    {
        if ($callable instanceof \ReflectionMethod) {
            $this->isInstanceMethod = true;
            $this->setMethodCallable($callable, $target);
        } else {
            $this->isInstanceMethod = false;
            $this->callable = $callable;
        }
    }

    private function setMethodCallable(\ReflectionMethod $method, $invocationObject): void
    {
        if (\is_object($invocationObject)) {
            $this->callable = $method;
            $this->invocationObject = $invocationObject;
        } elseif ($method->isStatic() || $method->isConstructor()) {
            $this->callable = $method;
            $this->invocationObject = null;
        } else {
            throw new \InvalidArgumentException(
                'ReflectionMethod callables must specify an invocation object'
            );
        }
    }

    public function __invoke(...$args)
    {
        if ($this->isInstanceMethod) {
            return $this->callable->invokeArgs($this->invocationObject, $args);
        }

        return $this->callable->isClosure()
            ? $this->invokeClosureCompat($args)
            : $this->callable->invokeArgs($args);
    }

    private function invokeClosureCompat(array $args): mixed
    {
        $scope = $this->callable->getClosureScopeClass();
        $closure = \Closure::bind(
            $this->callable->getClosure(),
            $this->callable->getClosureThis(),
            $scope->name ?? null
        );

        return $closure(...$args);
    }

    public function getCallable(): \ReflectionFunctionAbstract
    {
        return $this->callable;
    }

    public function __toString(): string
    {
        /* if (\is_string($callableOrMethodStr)) {
            $callableString = $callableOrMethodStr;
        } elseif (\is_array($callableOrMethodStr) && \array_key_exists(0, $callableOrMethodStr) && \array_key_exists(
            1,
            $callableOrMethodStr
        )) {
            if (\is_string($callableOrMethodStr[0]) && \is_string($callableOrMethodStr[1])) {
                $callableString = $callableOrMethodStr[0] . '::' . $callableOrMethodStr[1];
            } elseif (\is_object($callableOrMethodStr[0]) && \is_string($callableOrMethodStr[1])) {
                $callableString = \sprintf(
                    "[object(%s), '%s']",
                    \get_class($callableOrMethodStr[0]),
                    $callableOrMethodStr[1]
                );
            }
        } */

        // TODO Proper implementation
        return (string)$this->callable;
    }
}
