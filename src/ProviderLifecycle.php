<?php

namespace Amp\Injector;

interface ProviderLifecycle
{
    public function start(Context $context): void;

    public function stop(Context $context): void;
}