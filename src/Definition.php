<?php

namespace Amp\Injector;

use Amp\Injector\Meta\Type;

interface Definition
{
    public function getType(): ?Type;

    public function getAttribute(string $attribute): ?object;

    /**
     * @throws InjectionException
     */
    public function build(Injector $injector): Provider;
}
