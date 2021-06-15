<?php

namespace Amp\Injector;

use Amp\Injector\Internal\ExecutableWeaver;
use Amp\Injector\Meta\Executable;

final class Injector
{
    private Weaver $weaver;

    public function __construct(Weaver $weaver)
    {
        $this->weaver = $weaver;
    }

    /**
     * @throws InjectionException
     */
    public function getExecutableProvider(Executable $executable, Arguments $arguments): Provider
    {
        // TODO: Make customizable?
        return ExecutableWeaver::build($executable, $arguments->with($this->weaver), $this);
    }
}
