<?php

namespace Amp\Injector;

use Amp\Injector\Internal\ExecutableWeaver;
use Amp\Injector\Meta\Executable;
use Amp\Injector\Meta\Parameter;

final class Injector implements Weaver
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
        return ExecutableWeaver::build($executable, $arguments->with($this), $this);
    }

    public function getDefinition(Parameter $parameter): ?Definition
    {
        return $this->weaver->getDefinition($parameter);
    }
}
