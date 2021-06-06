<?php

namespace Amp\Injector\Internal;

interface ParentReflector
{
    public function getParents(string $class): array;
}
