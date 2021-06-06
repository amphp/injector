<?php

namespace Amp\Injector\Definition;

use Amp\Injector\Definition;
use Amp\Injector\Injector;
use Amp\Injector\Meta\Type;
use Amp\Injector\Provider;

final class ProviderDefinition implements Definition
{
    private Provider $provider;

    public function __construct(Provider $provider)
    {
        $this->provider = $provider;
    }

    public function getType(): ?Type
    {
        return null;
    }

    public function getAttribute(string $attribute): ?object
    {
        return null;
    }

    public function build(Injector $injector): Provider
    {
        return $this->provider;
    }
}
