<?php

namespace Amp\Injector;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

final class RootContext implements Context
{
    private ?TypeRules $typeRules;

    /** @var Provider[] */
    private array $providers = [];

    public function __construct(?TypeRules $rules = null)
    {
        $this->typeRules = $rules;
    }

    public function without(string $id): self
    {
        $clone = clone $this;
        unset($clone->providers[$id]);

        return $clone;
    }

    public function with(string $id, Provider $provider): self
    {
        if ($this->has($id)) {
            throw new \Error("Identifier conflict: '${id}' has already been defined");
        }

        $clone = clone $this;
        $clone->providers[$id] = $provider;

        return $clone;
    }

    public function has(string $id): bool
    {
        return isset($this->providers[$id]);
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function getType(string $class): object
    {
        if ($this->typeRules === null) {
            throw new InjectionException('No type rules present');
        }

        $identifier = $this->typeRules->get($class);
        if ($identifier === null) {
            throw new NotFoundException('No definition found for ' . $class);
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
}