<?php

namespace Amp\Injector;

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

    public static function merge(Arguments ...$arguments): Arguments
    {
        $arguments = \array_values($arguments);

        if (!$arguments) {
            return arguments();
        }

        $clone = clone \array_shift($arguments);
        foreach ($arguments as $argument) {
            foreach ($argument->name as $name => $provider) {
                $clone[$name] = $provider;
            }

            foreach ($argument->index as $index => $provider) {
                $clone[$index] = $provider;
            }

            foreach ($argument->attribute as $attribute => $provider) {
                $clone[$attribute] = $provider;
            }

            foreach ($argument->type as $type => $provider) {
                $clone[$type] = $provider;
            }
        }

        return $clone;
    }

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

    public function attribute(string $attributeClass, Provider $provider): self
    {
        $clone = clone $this;
        $clone->attribute[$attributeClass] = $provider;

        return $clone;
    }

    public function type(string $class, Provider $provider): self
    {
        $clone = clone $this;
        $clone->type[$class] = $provider;

        return $clone;
    }

    public function getByIndex(int $index): ?Provider
    {
        return $this->index[$index] ?? null;
    }

    public function getByName(string $name): ?Provider
    {
        return $this->name[$name] ?? null;
    }

    public function getTypes(): array
    {
        return $this->type;
    }

    public function getAttributes(): array
    {
        return $this->attribute;
    }
}