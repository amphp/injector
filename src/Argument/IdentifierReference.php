<?php

namespace Amp\Injector\Argument;

use Amp\Injector\ArgumentRule;
use Amp\Injector\Context;
use Amp\Injector\Executable;

final class IdentifierReference implements ArgumentRule
{
    private string $identifier;

    public function __construct(string $identifier)
    {
        $this->identifier = $identifier;
    }

    public function get(Context $context, Executable $executable): mixed
    {
        return $context->get($this->identifier);
    }
}