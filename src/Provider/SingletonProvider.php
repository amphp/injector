<?php

namespace Amp\Injector\Provider;

use Amp\Injector\InjectionException;
use Amp\Injector\Lifecycle;
use Amp\Injector\Provider;
use Amp\Injector\ProviderContext;

final class SingletonProvider implements Provider, Lifecycle
{
    private const STATUS_NONE = 0;
    private const STATUS_INITIALIZING = 1;
    private const STATUS_INITIALIZED = 2;
    private const STATUS_STARTING = 3;
    private const STATUS_STARTED = 4;
    private const STATUS_STOPPING = 5;
    private const STATUS_STOPPED = 6;

    private bool $lazy = false;

    private Provider $provider;

    private mixed $value;

    private int $status = self::STATUS_NONE;

    private array $onStart = [];
    private array $onStop = [];

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

    public function onStart(callable $callback): self
    {
        $clone = clone $this;
        $clone->onStart[] = $callback;

        return $clone;
    }

    public function onStop(callable $callback): self
    {
        $clone = clone $this;
        $clone->onStop[] = $callback;

        return $clone;
    }

    public function unwrap(): ?Provider
    {
        return $this->provider;
    }

    public function getDependencies(): array
    {
        return [];
    }

    public function start(): void
    {
        if (!$this->lazy) {
            $this->get(new ProviderContext);
        }
    }

    public function get(ProviderContext $context): mixed
    {
        // TODO: Locking?
        if ($this->status === self::STATUS_NONE) {
            $this->status = self::STATUS_INITIALIZING;
            $this->value = $this->provider->get(new ProviderContext); // hide context, because singleton
            $this->status = self::STATUS_INITIALIZED;
        } elseif ($this->status !== self::STATUS_STARTED) {
            throw new InjectionException('Failed to provide singleton due to lifecycle errors');
        }

        $this->status = self::STATUS_STARTING;

        foreach ($this->onStart as $callback) {
            $callback($this->value);
        }

        $this->status = self::STATUS_STARTED;

        return $this->value;
    }

    public function stop(): void
    {
        $this->status = self::STATUS_STOPPING;

        foreach ($this->onStop as $callback) {
            $callback($this->value);
        }

        $this->status = self::STATUS_STOPPED;
    }
}
