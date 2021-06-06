<?php

namespace Amp\Injector\Meta;

interface Executable
{
    /**
     * @return Parameter[]
     */
    public function getParameters(): array;

    public function getType(): ?Type;

    public function getAttribute(string $attribute): ?object;

    public function getDeclaringClass(): ?string;

    public function __invoke(...$args): mixed;

    public function __toString(): string;
}
