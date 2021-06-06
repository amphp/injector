<?php

namespace Amp\Injector\Meta\Reflection;

use Amp\Injector\Meta\Executable;
use Amp\Injector\Meta\Parameter;
use Amp\Injector\Meta\Type;

final class ReflectionFunctionParameter implements Parameter
{
    private \ReflectionParameter $reflection;
    private Executable $executable;

    public function __construct(\ReflectionParameter $reflection, Executable $executable)
    {
        $this->reflection = $reflection;
        $this->executable = $executable;
    }

    public function isOptional(): bool
    {
        return $this->reflection->isOptional();
    }

    public function isVariadic(): bool
    {
        return $this->reflection->isVariadic();
    }

    public function hasAttribute(string $attribute): bool
    {
        return !empty($this->reflection->getAttributes($attribute));
    }

    public function getDefaultValue(): mixed
    {
        try {
            return $this->reflection->getDefaultValue();
        } catch (\ReflectionException) {
            return null;
        }
    }

    public function __toString(): string
    {
        return 'parameter #' . $this->reflection->getPosition() . ' ($' . $this->getName() . ') in ' . $this->executable;
    }

    public function getName(): string
    {
        return $this->reflection->getName();
    }

    public function getType(): ?Type
    {
        return Type::fromReflection($this->reflection->getType());
    }

    public function getDeclaringClass(): ?string
    {
        return $this->executable->getDeclaringClass();
    }

    public function getAttribute(string $attribute): ?object
    {
        $attributes = $this->reflection->getAttributes($attribute);
        if (isset($attributes[0])) {
            return $attributes[0]->newInstance();
        }

        return null;
    }

    public function getDeclaringFunction(): ?string
    {
        return $this->reflection->getDeclaringFunction()->getName();
    }
}
