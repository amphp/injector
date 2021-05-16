<?php

namespace Amp\Injector;

use Amp\Injector\Provider\OptionalType;
use Amp\Injector\Provider\Type;
use Amp\Injector\Provider\Unknown;
use Amp\Injector\Provider\Value;

final class Arguments
{
    /** @var Provider[] */
    private array $name = [];
    /** @var Provider[] */
    private array $index = [];
    /** @var Provider[] */
    private array $attribute = [];
    /** @var Provider[] */
    private array $type = [];

    public function index(int $index, Provider $provider): self
    {
        $clone = clone $this;
        $clone->index[$index] = $provider;

        return $clone;
    }

    public function name(string $name, Provider $provider): self
    {
        $clone = clone $this;
        $clone->name[$name] = $provider;

        return $clone;
    }

    public function attribute(string $name, Provider $provider): self
    {
        $clone = clone $this;
        $clone->attribute[$name] = $provider;

        return $clone;
    }

    public function type(string $name, Provider $provider): self
    {
        $clone = clone $this;
        $clone->type[$name] = $provider;

        return $clone;
    }

    /**
     * @return Provider[]
     *
     * @throws InjectionException
     */
    public function resolve(Executable $executable): array
    {
        $parameters = $executable->getCallable()->getParameters();

        $argumentCount = $executable->getNumberOfParameters();
        $arguments = [];

        for ($index = 0; $index < $argumentCount; $index++) {
            $parameter = $parameters[$index];
            $name = $parameter->getName();

            $indexProvider = $this->index[$index] ?? null;
            $nameProvider = $this->name[$name] ?? null;

            if ($indexProvider && $nameProvider) {
                throw new InjectionException('Named argument overrides positional argument for argument #' . $index . ' ($' . $name . ')');
            }

            $arguments[$index] ??= $indexProvider;
            $arguments[$index] ??= $nameProvider;
            $arguments[$index] ??= $this->resolveAttributes($parameter);
            $arguments[$index] ??= $this->resolveTypes($parameter->getType());
            $arguments[$index] ??= $this->resolveFallback($index, $parameter, $executable);
        }

        return $arguments;
    }

    private function resolveAttributes(\ReflectionParameter $parameter): ?Provider
    {
        $candidates = [];

        foreach ($this->attribute as $attribute => $candidate) {
            if ($parameter->getAttributes($attribute)) {
                $candidates[] = $candidate;
            }
        }

        return $this->getUnique($candidates);
    }

    /**
     * @param Provider[] $candidates
     * @return Provider|null
     * @throws InjectionException
     */
    private function getUnique(array $candidates): ?Provider
    {
        $count = \count($candidates);
        if ($count === 0) {
            return null;
        }

        if ($count === 1) {
            return $candidates[0];
        }

        // TODO: Parameter in message
        throw new InjectionException('Unable to choose provider, because multiple providers apply and conflict');
    }

    private function resolveTypes(?\ReflectionType $reflectionType): ?Provider
    {
        if ($reflectionType === null) {
            return null;
        }

        $candidates = [];

        foreach ($this->type as $type => $candidate) {
            if ($reflectionType instanceof \ReflectionNamedType) {
                if (\is_a($type, $reflectionType->getName(), true)) {
                    $candidates[] = $candidate;
                }
            } else if ($reflectionType instanceof \ReflectionUnionType) {
                foreach ($reflectionType->getTypes() as $unionType) {
                    $unionCandidate = $this->resolveTypes($unionType);
                    if ($unionCandidate !== null) {
                        $candidates[] = $unionCandidate;
                    }
                }
            } else {
                throw new InjectionException('Unknown type "' . \get_class($reflectionType) . '"');
            }
        }

        return $this->getUnique($candidates);
    }

    private function resolveFallback(int $index, \ReflectionParameter $parameter, Executable $executable): Provider
    {
        $type = $parameter->getType();
        if ($type === null) {
            return $parameter->isOptional()
                ? new Value($parameter->getDefaultValue())
                : new Unknown($index, $executable);
        }

        if ($type instanceof \ReflectionNamedType) {
            return $parameter->isOptional()
                ? new OptionalType($type->getName(), new Value($parameter->getDefaultValue()))
                : new Type($type->getName());
        }

        if ($parameter->isOptional()) {
            return new Value($parameter->getDefaultValue());
        }

        if ($type instanceof \ReflectionUnionType) {
            throw new InjectionException('Cannot choose between multiple types for parameter');
        }

        return new Unknown($index, $executable);
    }
}