<?php

namespace Amp\Injector;

interface Lifecycle
{
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