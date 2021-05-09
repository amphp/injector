<?php

namespace Amp\Injector;

use Amp\Injector\Argument\TypeReference;
use Amp\Injector\Argument\Unknown;

// TODO: Autoboxing everything that doesn't implement ArgumentRule to Value?
final class ArgumentRules
{
    /** @var ArgumentRule[] */
    private array $nameRules = [];
    /** @var ArgumentRule[] */
    private array $indexRules = [];
    /** @var ArgumentRule[] */
    private array $attributeRules = [];
    /** @var ArgumentRule[] */
    private array $typeRules = [];
    /** @var ArgumentRule|null */
    private ?ArgumentRule $fallback = null;

    public function index(int $index, ArgumentRule $rule): self
    {
        $clone = clone $this;
        $clone->indexRules[$index] = $rule;

        return $clone;
    }

    public function name(string $name, ArgumentRule $rule): self
    {
        $clone = clone $this;
        $clone->nameRules[$name] = $rule;

        return $clone;
    }

    public function attribute(string $name, ArgumentRule $rule): self
    {
        $clone = clone $this;
        $clone->attributeRules[$name] = $rule;

        return $clone;
    }

    public function type(string $name, ArgumentRule $rule): self
    {
        $clone = clone $this;
        $clone->typeRules[$name] = $rule;

        return $clone;
    }

    public function fallback(ArgumentRule $rule): self
    {
        $clone = clone $this;
        $clone->fallback = $rule;

        return $clone;
    }

    /**
     * @return ArgumentRule[]
     *
     * @throws InjectionException
     */
    public function resolve(Executable $executable): array
    {
        // TODO Move somewhere else, as it contains logic
        $reflectionParameters = $executable->getCallable()->getParameters();

        $argumentCount = $this->resolveArgumentCount($executable);
        $arguments = [];

        for ($i = 0; $i < $argumentCount; $i++) {
            $reflectionParameter = $reflectionParameters[$i];

            $arguments[$i] ??= $this->indexRules[$i] ?? null;
            $arguments[$i] ??= $this->nameRules[$reflectionParameter->getName()] ?? null;
            $arguments[$i] ??= $this->resolveAttributeRules($reflectionParameter);
            $arguments[$i] ??= $this->resolveTypeRules($reflectionParameter->getType());
            $arguments[$i] ??= $this->fallback;
            $arguments[$i] ??= $this->resolveTypeFallback($i, $reflectionParameter->getType());
        }

        return $arguments;
    }

    // TODO Autowire required arguments without definitions
    private function resolveArgumentCount(Executable $executable): int
    {
        $count = $executable->getCallable()->getNumberOfRequiredParameters();
        $namePosition = [];

        $reflectionParameters = $executable->getCallable()->getParameters();
        foreach ($reflectionParameters as $reflectionParameter) {
            $namePosition[$reflectionParameter->getName()] = $reflectionParameter->getPosition();
        }

        foreach ($this->indexRules as $index => $rule) {
            if ($index + 1 > $count) {
                $count = $index + 1;
            }
        }

        foreach ($this->nameRules as $name => $rule) {
            if (!isset($namePosition[$name])) {
                throw new InjectionException('Unknown parameter "' . $name . '"');
            }

            $count = \max($count, $namePosition[$name] + 1);
        }

        return $count;
    }

    private function resolveAttributeRules(\ReflectionParameter $parameter): ?ArgumentRule
    {
        $applicableRules = [];

        foreach ($this->attributeRules as $attribute => $rule) {
            if ($parameter->getAttributes($attribute)) {
                $applicableRules[] = $rule;
            }
        }

        $count = \count($applicableRules);
        if ($count === 0) {
            return null;
        }

        if ($count === 1) {
            return $applicableRules[0];
        }

        throw new InjectionException('Multiple attribute rules apply and conflict for parameter ' . $parameter->getName());
    }

    private function resolveTypeRules(?\ReflectionType $reflectionType): ?ArgumentRule
    {
        if ($reflectionType === null) {
            return null;
        }

        $applicableRules = [];

        foreach ($this->typeRules as $type => $rule) {
            if ($reflectionType instanceof \ReflectionNamedType) {
                if (\is_a($type, $reflectionType->getName(), true)) {
                    $applicableRules[] = $rule;
                }
            } else if ($reflectionType instanceof \ReflectionUnionType) {
                foreach ($reflectionType->getTypes() as $reflectionTypeCase) {
                    $caseRule = $this->resolveTypeRules($reflectionTypeCase);
                    if ($caseRule !== null) {
                        $applicableRules[] = $caseRule;
                    }
                }
            } else {
                throw new InjectionException('Unknown type "' . \get_class($reflectionType) . '"');
            }
        }

        $count = \count($applicableRules);
        if ($count === 0) {
            return null;
        }

        if ($count === 1) {
            return $applicableRules[0];
        }

        // TODO: Order by hierarchy, only error if same level in hierarchy
        throw new InjectionException('Multiple type rules apply and conflict for parameter');
    }

    private function resolveTypeFallback(int $index, ?\ReflectionType $reflectionType): ArgumentRule
    {
        if ($reflectionType === null) {
            return new Unknown($index);
        }

        if ($reflectionType instanceof \ReflectionNamedType) {
            return new TypeReference($reflectionType->getName());
        }

        if ($reflectionType instanceof \ReflectionUnionType) {
            throw new InjectionException('Cannot choose between multiple types for parameter');
        }

        return new Unknown($index);
    }
}