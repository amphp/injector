<?php

namespace Amp\Injector;

interface LifecycleListener
{
    public function start(Context $context): void;

    public function stop(Context $context): void;
}