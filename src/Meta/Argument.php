<?php

namespace Amp\Injector\Meta;

use Amp\Injector\Provider;

final class Argument
{
    private Parameter $parameter;
    private Provider $provider;

    public function __construct(Parameter $parameter, Provider $provider)
    {
        $this->parameter = $parameter;
        $this->provider = $provider;
    }

    public function getParameter(): Parameter
    {
        return $this->parameter;
    }

    public function getProvider(): Provider
    {
        return $this->provider;
    }
}
