<?php

namespace Amp\Injector\Provider;

use Amp\Injector\Context;
use Amp\Injector\LifecycleListener;
use Amp\Injector\Provider;

final class Singleton implements Provider, LifecycleListener
{
    private Provider $provider;

    private bool $started = false;
    private bool $lazy = false;

    private mixed $value;

    public function __construct(Provider $provider)
    {
        $this->provider = $provider;
    }

    public function lazy(): self
    {
        $clone = clone $this;
        $clone->lazy = true;

        return $clone;
    }

    public function eager(): self
    {
        $clone = clone $this;
        $clone->lazy = false;

        return $clone;
    }

    public function get(Context $context): mixed
    {
        if (!$this->started) {
            $this->value = $this->provider->get($context);
            $this->started = true;
        }

        return $this->value;
    }

    public function getType(): ?string
    {
        return $this->provider->getType();
    }

    public function getDependencies(Context $context): array
    {
        return [$this->provider];
    }

    public function start(Context $context): void
    {
        if ($this->lazy) {
            return;
        }

        $this->value = $this->provider->get($context);
        $this->started = true;
    }

    public function stop(Context $context): void
    {
        $this->value = null;
        $this->started = false;
    }
}