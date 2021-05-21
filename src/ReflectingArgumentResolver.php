<?php

namespace Amp\Injector;

use Amp\Injector\Provider\OptionalTypeReference;
use Amp\Injector\Provider\TypeReference;
use Amp\Injector\Provider\ValueProvider;

final class ReflectingArgumentResolver implements ArgumentResolver
{
    public function resolve(Executable $executable, Arguments $arguments): array
    {
        try {
            $parameters = $executable->getCallable()->getParameters();

            $argumentCount = $executable->getNumberOfParameters();
            $args = [];

            for ($index = 0; $index < $argumentCount; $index++) {
                $parameter = $parameters[$index];
                $name = $parameter->getName();

                $indexProvider = $arguments->getByIndex($index);
                $nameProvider = $arguments->getByName($name);

                if ($indexProvider && $nameProvider) {
                    throw new InjectionException('Named argument overrides positional argument for argument #' . $index . ' ($' . $name . ')');
                }

                $args[$index] ??= $indexProvider;
                $args[$index] ??= $nameProvider;
                $args[$index] ??= $this->resolveAttributes($arguments, $parameter);
                $args[$index] ??= $this->resolveTypes($arguments, $parameter->getType());

                if (!$parameter->isVariadic()) {
                    $args[$index] ??= $this->resolveFallback($index, $parameter);
                } else if ($args[$index] === null) {
                    unset($args[$index]);
                }
            }

            return $args;
        } catch (\ReflectionException $e) {
            throw new InjectionException('Unable to determine arguments: ' . $e->getMessage(), $e);
        }
    }

    /**
     * @throws InjectionException
     */
    private function resolveAttributes(Arguments $arguments, \ReflectionParameter $parameter): ?Provider
    {
        $candidates = [];

        foreach ($arguments->getAttributes() as $attribute => $candidate) {
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

    /**
     * @throws InjectionException
     */
    private function resolveTypes(Arguments $arguments, ?\ReflectionType $reflectionType): ?Provider
    {
        if ($reflectionType === null) {
            return null;
        }

        $candidates = [];

        foreach ($arguments->getTypes() as $type => $candidate) {
            if ($reflectionType instanceof \ReflectionNamedType) {
                if (\is_a($type, $reflectionType->getName(), true)) {
                    $candidates[] = $candidate;
                }
            } else if ($reflectionType instanceof \ReflectionUnionType) {
                foreach ($reflectionType->getTypes() as $unionType) {
                    $unionCandidate = $this->resolveTypes($arguments, $unionType);
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

    /**
     * @throws InjectionException|\ReflectionException
     */
    private function resolveFallback(int $index, \ReflectionParameter $parameter): Provider
    {
        $type = $parameter->getType();
        if ($type === null) {
            if (!$parameter->isOptional()) {
                throw new InjectionException('Failed to determine argument #' . $index . ' ($' . $parameter->getName() . '), because no definition matches');
            }

            return new ValueProvider($parameter->getDefaultValue());
        }

        if ($type instanceof \ReflectionNamedType) {
            return $parameter->isOptional()
                ? new OptionalTypeReference($type->getName(), new ValueProvider($parameter->getDefaultValue()))
                : new TypeReference($type->getName());
        }

        if ($parameter->isOptional()) {
            return new ValueProvider($parameter->getDefaultValue());
        }

        if ($type instanceof \ReflectionUnionType) {
            throw new InjectionException('Failed to determine argument #' . $index . ' ($' . $parameter->getName() . '), because union types cannot be automatically be resolved');
        }

        throw new InjectionException('Failed to determine argument #' . $index . ' ($' . $parameter->getName() . '), because no definition matches');
    }
}