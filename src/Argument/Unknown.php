<?php

namespace Amp\Injector\Argument;

use Amp\Injector\ArgumentRule;
use Amp\Injector\Context;
use Amp\Injector\Executable;
use Amp\Injector\InjectionException;

final class Unknown implements ArgumentRule
{
    private int $index;

    public function __construct(int $index)
    {
        $this->index = $index;
    }

    public function get(Context $context, Executable $executable): mixed
    {
        $name = $executable->getCallable()->getParameters()[$this->index]->getName();

        throw new InjectionException('Failed to provide argument #' . $this->index . ' ($' . $name . '), because no definition exists to provide it to ' . $executable);
    }
}