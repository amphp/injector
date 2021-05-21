<?php

namespace Amp\Injector;

interface ApplicationContext extends Context
{
    /**
     * @throws LifecycleException
     */
    public function instantiate(): void;

    /**
     * @throws LifecycleException
     */
    public function start(): void;

    /**
     * @throws LifecycleException
     */
    public function stop(): void;

    public function isRunning(): bool;
}