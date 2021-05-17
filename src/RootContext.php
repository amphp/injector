<?php

namespace Amp\Injector;

use Amp\Injector\ImplementationResolver\NullImplementationResolver;
use Amp\Injector\Internal\FiberLocalStack;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

final class RootContext implements Context
{
    private ImplementationResolver $implementationResolver;

    /** @var Provider[] */
    private array $providers = [];

    private FiberLocalStack $dependents;

    public function __construct()
    {
        $this->implementationResolver = new NullImplementationResolver;
        $this->dependents = new FiberLocalStack;
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function getType(string $class): object
    {
        $identifier = $this->implementationResolver->get($class);
        if ($identifier === null) {
            throw new NotFoundException('No implementation found for type ' . $class);
        }

        return $this->get($identifier);
    }

    public function get(string $id): mixed
    {
        if (!isset($this->providers[$id])) {
            throw new NotFoundException('Unknown identifier: ' . $id);
        }

        $this->dependents->push($id);

        try {
            return $this->providers[$id]->get($this);
        } finally {
            $this->dependents->pop();
        }
    }

    public function hasType(string $class): bool
    {
        $identifier = $this->implementationResolver->get($class);
        if ($identifier === null) {
            return false;
        }

        return $this->has($identifier);
    }

    public function has(string $id): bool
    {
        return isset($this->providers[$id]);
    }

    /**
     * @throws NotFoundException
     */
    public function getTypeProvider(string $class): Provider
    {
        $identifier = $this->implementationResolver->get($class);
        if ($identifier === null) {
            throw new NotFoundException('No implementation found for type ' . $class);
        }

        return $this->getProvider($identifier);
    }

    /**
     * @throws NotFoundException
     */
    public function getProvider(string $id): Provider
    {
        if (isset($this->providers[$id])) {
            return $this->providers[$id];
        }

        throw new NotFoundException('Unknown identifier: ' . $id);
    }

    public function getDependents(): array
    {
        $dependents = $this->dependents->toArray();
        if ($dependents) {
            unset($dependents[array_key_last($dependents)]);
        }

        return $dependents;
    }

    /**
     * @throws InjectionException
     */
    public function with(string $id, Provider $provider): self
    {
        if ($this->has($id)) {
            throw new InjectionException('Identifier conflict: ' . $id);
        }

        $clone = clone $this;
        $clone->providers[$id] = $provider;

        return $clone;
    }

    public function withImplementationResolver(ImplementationResolver $implementationResolver): self
    {
        $clone = clone $this;
        $clone->implementationResolver = $implementationResolver;

        return $clone;
    }
}