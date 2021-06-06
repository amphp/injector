<?php

namespace Amp\Injector\Internal;

use Amp\Injector\InjectionException;
use Amp\Injector\Provider;
use Amp\Injector\ProviderContext;

/** @internal */
final class LocalProvider implements Provider
{
    private Provider $provider;
    private FiberLocalObjectSet $pending;

    public function __construct(Provider $provider, FiberLocalObjectSet $pending)
    {
        $this->provider = $provider;
        $this->pending = $pending;
    }

    public function get(ProviderContext $context): mixed
    {
        try {
            if ($this->pending->contains($this->provider)) {
                throw new InjectionException('Recursive dependency detected');
            }

            $this->pending->add($this->provider);

            return $this->provider->get($context);
        } finally {
            $this->pending->remove($this->provider);
        }
    }

    public function unwrap(): ?Provider
    {
        return $this->provider;
    }

    public function getDependencies(): array
    {
        return [];
    }
}
