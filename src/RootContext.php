<?php

namespace Amp\Injector;

use Amp\Injector\ImplementationResolver\NullImplementationResolver;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

final class RootContext implements Context
{
    private ImplementationResolver $implementationResolver;

    /** @var Provider[] */
    private array $providers = [];

    public function __construct()
    {
        $this->implementationResolver = new NullImplementationResolver;
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
        if (isset($this->providers[$id])) {
            return $this->providers[$id]->get($this);
        }

        throw new NotFoundException('Unknown identifier: ' . $id);
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