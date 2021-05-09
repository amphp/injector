<?php

namespace Amp\Injector\Argument;

use Amp\Injector\ArgumentRule;
use Amp\Injector\Context;
use Amp\Injector\Executable;

final class TypeReference implements ArgumentRule
{
    private string $type;

    public function __construct(string $type)
    {
        $this->type = $type;
    }

    public function get(Context $context, Executable $executable): object
    {
        return $context->getType($this->type);
    }
}