<?php

namespace Amp\Injector\Provider;

use Amp\Injector\Context;
use Amp\Injector\InjectionException;
use Amp\Injector\Provider;
use Amp\Injector\ProviderLifecycle;

final class SingletonProvider implements Provider, ProviderLifecycle
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

    public function get(Context $context): mixed
    {
        // TODO: Locking?
        if ($this->lazy && $this->status === self::STATUS_NONE) {
            $this->status = self::STATUS_STARTING;

            $this->value = $this->provider->get($context);

            foreach ($this->onStart as $callback) {
                $callback($this->value, $context);
            }

            $this->status = self::STATUS_STARTED;
        } else if ($this->status < self::STATUS_INITIALIZED) {
            throw new InjectionException('Failed to provide singleton due to lifecycle errors');
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

    public function instantiate(Context $context): void
    {
        if ($this->lazy) {
            return;
        }

        $this->status = self::STATUS_INITIALIZING;
        $this->value = $this->provider->get($context);
        $this->status = self::STATUS_INITIALIZED;
    }

    public function start(Context $context): void
    {
        if ($this->lazy) {
            return;
        }

        $this->status = self::STATUS_STARTING;

        foreach ($this->onStart as $callback) {
            $callback($this->value, $context);
        }

        $this->status = self::STATUS_STARTED;
    }

    public function stop(Context $context): void
    {
        $this->status = self::STATUS_STOPPING;

        foreach ($this->onStop as $callback) {
            $callback($this->value, $context);
        }

        $this->status = self::STATUS_STOPPED;
    }
}