<?php

namespace Amp\Injector;

interface Lifecycle
{
    public function start(): void;

    public function stop(): void;
}
