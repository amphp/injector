<?php

namespace Amp\Injector;

use Amp\Injector\ImplementationResolver\NullImplementationResolver;
use Amp\Injector\Internal\FiberLocalStack;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

final class RootContext implements ApplicationContext
{
    private const STATUS_NONE = 0;
    private const STATUS_INSTANTIATING = 1;
    private const STATUS_INSTANTIATED = 2;
    private const STATUS_STARTING = 3;
    private const STATUS_RUNNING = 4;
    private const STATUS_STOPPING = 5;
    private const STATUS_STOPPED = 6;

    private ImplementationResolver $implementationResolver;

    /** @var Provider[] */
    private array $providers = [];

    private FiberLocalStack $dependents;

    private int $status = self::STATUS_NONE;

    /** @var ProviderLifecycle[] */
    private array $lifecycleProviders = [];

    public function __construct()
    {
        $this->implementationResolver = new NullImplementationResolver;
        $this->dependents = new FiberLocalStack;
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
        if (!isset($this->providers[$id])) {
            throw new NotFoundException('Unknown identifier: ' . $id);
        }

        $this->dependents->push($id);

        try {
            return $this->providers[$id]->get($this);
        } finally {
            $this->dependents->pop();
        }
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

    public function getIds(): array
    {
        return \array_map('strval', \array_keys($this->providers));
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

    public function getDependents(): array
    {
        $dependents = $this->dependents->toArray();
        if ($dependents) {
            unset($dependents[array_key_last($dependents)]);
        }

        return $dependents;
    }

    /**
     * @throws InjectionException
     * @throws LifecycleException
     */
    public function with(string $id, Provider $provider): self
    {
        if ($this->status !== self::STATUS_NONE) {
            throw new LifecycleException('Context already started');
        }

        if ($this->has($id)) {
            throw new InjectionException('Identifier conflict: ' . $id);
        }

        $clone = clone $this;
        $clone->providers[$id] = $provider;

        return $clone;
    }

    /**
     * @throws LifecycleException
     */
    public function withImplementationResolver(ImplementationResolver $implementationResolver): self
    {
        if ($this->status !== self::STATUS_NONE) {
            throw new LifecycleException('Context already started');
        }

        $clone = clone $this;
        $clone->implementationResolver = $implementationResolver;

        return $clone;
    }

    public function instantiate(): void
    {
        if ($this->status !== self::STATUS_NONE) {
            throw new LifecycleException('Invalid operation for context status');
        }

        $this->status = self::STATUS_INSTANTIATING;

        try {
            foreach ($this->providers as $provider) {
                $this->instantiateProvider($provider);
            }
        } catch (\Throwable $e) {
            try {
                $this->stop();
            } finally {
                // TODO: Wrap exception?
                throw $e;
            }
        }

        $this->status = self::STATUS_INSTANTIATED;
    }

    private function instantiateProvider(Provider $provider): void
    {
        foreach ($provider->getDependencies($this) as $dependency) {
            $this->instantiateProvider($dependency);
        }

        if ($provider instanceof ProviderLifecycle) {
            $providerId = \spl_object_id($provider);

            if (!isset($this->lifecycleProviders[$providerId])) {
                $this->lifecycleProviders[$providerId] = $provider;
                $provider->instantiate($this);
            }
        }
    }

    public function stop(): void
    {
        // TODO: Protect against stop() being called form outside if starting
        if ($this->status !== self::STATUS_STARTING && !$this->isRunning()) {
            throw new LifecycleException('Invalid operation for context status');
        }

        $this->status = self::STATUS_STOPPING;

        foreach (\array_reverse($this->lifecycleProviders) as $provider) {
            $provider->stop($this);
        }

        $this->status = self::STATUS_STOPPED;
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function start(): void
    {
        if ($this->status !== self::STATUS_INSTANTIATED) {
            throw new LifecycleException('Invalid operation for context status');
        }

        $this->status = self::STATUS_STARTING;

        foreach ($this->lifecycleProviders as $provider) {
            $provider->start($this);
        }

        $this->status = self::STATUS_RUNNING;
    }
}