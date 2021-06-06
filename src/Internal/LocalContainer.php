<?php

namespace Amp\Injector\Internal;

use Amp\Injector\Container;
use Amp\Injector\InjectionException;
use Amp\Injector\Injector;
use Amp\Injector\NotFoundException;
use Amp\Injector\Provider;
use Amp\Injector\ProviderContext;

/** @internal */
final class LocalContainer implements Container
{
    /** @var Provider[] */
    private array $providers = [];

    private FiberLocalObjectSet $pending;

    /**
     * @throws InjectionException
     */
    public function __construct(Injector $injector)
    {
        $this->pending = new FiberLocalObjectSet;

        foreach ($injector->getDefinitions() as $id => $definition) {
            $this->providers[$id] = new LocalProvider($definition->build($injector), $this->pending);
        }
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
