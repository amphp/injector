<?php

namespace Amp\Injector;

interface ProviderLifecycle
{
    public function instantiate(Context $context): void;

    public function start(Context $context): void;

    public function stop(Context $context): void;
}