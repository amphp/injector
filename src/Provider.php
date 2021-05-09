<?php

namespace Amp\Injector;

interface Provider
{
    public function get(Context $context): mixed;
}