<?php

namespace Amp\Injector\Argument;

use Amp\Injector\ArgumentRule;
use Amp\Injector\Context;
use Amp\Injector\Executable;

final class Delegate implements ArgumentRule
{
    private $callable;

    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    public function get(Context $context, Executable $executable): mixed
    {
        return ($this->callable)($context, $executable);
    }
}