<?php

namespace Amp\Injector\Meta\Reflection;

use Amp\Injector\Meta\Executable;
use Amp\Injector\Meta\Type;

final class ReflectionFunctionExecutable implements Executable
{
    private \ReflectionFunctionAbstract $callable;
    private ?object $invocationObject;
    private bool $isInstanceMethod;

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
                'Methods must specify a target object'
            );
        }
    }

    public function getParameters(): array
    {
        $parameters = [];

        foreach ($this->callable->getParameters() as $parameter) {
            $parameters[] = new ReflectionFunctionParameter($parameter, $this);
        }

        return $parameters;
    }

    public function __invoke(...$args): mixed
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

    public function __toString(): string
    {
        if ($this->callable instanceof \ReflectionMethod) {
            $separator = $this->callable->isStatic() ? '::' : '->';

            return $this->callable->getDeclaringClass()->getName() . $separator . $this->callable->getName() . '()';
        } elseif ($this->callable instanceof \ReflectionFunction) {
            if ($this->callable->isClosure()) {
                return $this->callable->getName() . '() defined in ' . $this->callable->getFileName() . ':' . $this->callable->getStartLine();
            }

            return $this->callable->getName() . '()';
        }
        return 'unknown callable';
    }

    public function getDeclaringClass(): ?string
    {
        if ($this->callable instanceof \ReflectionMethod) {
            return $this->callable->getDeclaringClass()->getName();
        }

        return null;
    }

    public function getType(): ?Type
    {
        return Type::fromReflection($this->callable->getReturnType());
    }

    public function getAttribute(string $attribute): ?object
    {
        if ($attributes = $this->callable->getAttributes($attribute)) {
            return $attributes[0]->newInstance();
        }

        return null;
    }
}
