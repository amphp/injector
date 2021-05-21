<?php

namespace Amp\Injector;

interface ArgumentResolver
{
    /**
     * @return Provider[]
     *
     * @throws InjectionException
     */
    public function resolve(Executable $executable, Arguments $arguments): array;
}