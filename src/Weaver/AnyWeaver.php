<?php

namespace Amp\Injector\Weaver;

use Amp\Injector\Definition;
use Amp\Injector\Meta\Parameter;
use Amp\Injector\Weaver;

final class AnyWeaver implements Weaver
{
    private array $weavers;

    public function __construct(Weaver ...$weavers)
    {
        $this->weavers = $weavers;
    }

    public function getDefinition(Parameter $parameter): ?Definition
    {
        foreach ($this->weavers as $weaver) {
            if ($definition = $weaver->getDefinition($parameter)) {
                return $definition;
            }
        }

        return null;
    }
}
