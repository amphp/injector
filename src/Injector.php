<?php

namespace Amp\Injector;

use Amp\Injector\Internal\ExecutableWeaver;
use Amp\Injector\Meta\Executable;
use Amp\Injector\Meta\Parameter;

final class Injector implements Weaver
{
    private Definitions $definitions;
    private Weaver $weaver;

    public function __construct(Definitions $definitions, Weaver $weaver)
    {
        $this->definitions = $definitions;
        $this->weaver = $weaver;
    }

    public function getDefinitions(): Definitions
    {
        return $this->definitions;
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
