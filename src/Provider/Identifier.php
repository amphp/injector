<?php

namespace Amp\Injector\Provider;

use Amp\Injector\Context;
use Amp\Injector\Provider;

final class Identifier implements Provider
{
    private string $identifier;

    public function __construct(string $identifier)
    {
        $this->identifier = $identifier;
    }

    public function get(Context $context): mixed
    {
        return $context->get($this->identifier);
    }

    public function getType(): ?string
    {
        return null;
    }

    public function getDependencies(Context $context): array
    {
        return [$context->getProvider($this->identifier)];
    }
}