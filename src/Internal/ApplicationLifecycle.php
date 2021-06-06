<?php

namespace Amp\Injector\Internal;

use Amp\Injector\Container;
use Amp\Injector\Lifecycle;
use Amp\Injector\LifecycleException;
use Amp\Injector\Provider;

// TODO: Class naming
/** @internal */
final class ApplicationLifecycle
{
    private const STATUS_NONE = 0;
    private const STATUS_STARTING = 1;
    private const STATUS_RUNNING = 2;
    private const STATUS_STOPPING = 3;
    private const STATUS_STOPPED = 4;

    private int $status = self::STATUS_NONE;

    private Container $container;

    /** @var Lifecycle[] */
    private array $managed = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @throws \Throwable
     * @throws LifecycleException
     */
    public function start(): void
    {
        if ($this->status !== self::STATUS_NONE) {
            throw new LifecycleException('Invalid operation for lifecycle status');
        }

        $this->status = self::STATUS_STARTING;

        try {
            foreach ($this->container as $provider) {
                $this->startProvider($provider);
            }
        } catch (\Throwable $e) {
            try {
                $this->stop();
            } finally {
                throw $e;
            }
        }

        $this->status = self::STATUS_RUNNING;
    }

    private function startProvider(Provider $provider): void
    {
        if ($parent = $provider->unwrap()) {
            $this->startProvider($parent);
        }

        foreach ($provider->getDependencies() as $dependency) {
            $this->startProvider($dependency);
        }

        if ($provider instanceof Lifecycle) {
            $providerId = \spl_object_id($provider);

            if (!isset($this->managed[$providerId])) {
                $this->managed[$providerId] = $provider;
                $provider->start();
            }
        }
    }

    /**
     * @throws LifecycleException
     */
    public function stop(): void
    {
        // TODO: Protect against stop() being called form outside if starting
        if ($this->status !== self::STATUS_STARTING && !$this->isRunning()) {
            throw new LifecycleException('Invalid operation for context status');
        }

        $this->status = self::STATUS_STOPPING;

        foreach (\array_reverse($this->managed) as $provider) {
            $provider->stop();
        }

        $this->status = self::STATUS_STOPPED;
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }
}
