<?php

namespace Amp\Injector\Argument;

use Amp\Injector\ArgumentRule;
use Amp\Injector\Context;
use Amp\Injector\Executable;

final class Value implements ArgumentRule
{
    private mixed $value;

    public function __construct(mixed $value)
    {
        $this->value = $value;
    }

    public function get(Context $context, Executable $executable): mixed
    {
        return $this->value;
    }
}