<?php

namespace Amp\Injector;

use Amp\Injector\Meta\Parameter;

interface Weaver
{
    /**
     * @throws InjectionException
     */
    public function getDefinition(Parameter $parameter): ?Definition;
}
