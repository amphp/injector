<?php

namespace Amp\Injector\Provider;

use Amp\Injector\Context;
use Amp\Injector\Provider;

final class SingletonProvider implements Provider
{
    private Provider $factory;

    private bool $initialized = false;

    private mixed $value;

    public function __construct(Provider $factory)
    {
        $this->factory = $factory;
    }

    public function get(Context $context): mixed
    {
        if (!$this->initialized) {
            $this->value = $this->factory->get($context);
            $this->initialized = true;
        }

        return $this->value;
    }
}