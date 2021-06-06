<?php

namespace Amp\Injector;

use Amp\Injector\Internal\ApplicationLifecycle;

final class Application implements Lifecycle
{
    private Injector $injector;
    private Container $container;
    private ApplicationLifecycle $lifecycle;

    /**
     * @throws InjectionException
     */
    public function __construct(Injector $injector)
    {
        $this->injector = $injector;
        $this->container = new RootContainer;

        foreach ($injector->getDefinitions() as $id => $definition) {
            $this->container = $this->container->with($id, $definition->build($injector));
        }

        $this->lifecycle = new ApplicationLifecycle($this->container);
    }

    public function getInjector(): Injector
    {
        return $this->injector;
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * @throws InjectionException
     */
    public function invoke(Definition $definition)
    {
        return $definition->build($this->injector)->get(new ProviderContext);
    }

    /**
     * @throws \Throwable
     * @throws LifecycleException
     */
    public function start(): void
    {
        $this->lifecycle->start();
    }

    /**
     * @throws LifecycleException
     */
    public function stop(): void
    {
        $this->lifecycle->stop();
    }
}
