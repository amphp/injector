<?php

namespace Amp\Injector;

final class RootContainer implements Container
{
    /** @var Provider[] */
    private array $providers = [];

    public function with(string $id, Provider $provider): self
    {
        $clone = clone $this;
        $clone->providers[$id] = $provider;

        return $clone;
    }

    public function get(string $id): mixed
    {
        return $this->getProvider($id)->get(new ProviderContext);
    }

    /**
     * @throws NotFoundException
     */
    public function getProvider(string $id): Provider
    {
        return $this->providers[$id] ?? throw new NotFoundException('Unknown identifier: ' . $id);
    }

    public function has(string $id): bool
    {
        return isset($this->providers[$id]);
    }

    public function getIterator(): iterable
    {
        foreach ($this->providers as $id => $provider) {
            yield (string) $id => $provider;
        }
    }
}
