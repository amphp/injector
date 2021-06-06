<?php

namespace Amp\Injector\Meta;

interface Parameter
{
    public function getName(): string;

    public function isOptional(): bool;

    public function isVariadic(): bool;

    public function getType(): ?Type;

    public function hasAttribute(string $attribute): bool;

    public function getAttribute(string $attribute): ?object;

    public function getDefaultValue(): mixed;

    public function getDeclaringClass(): ?string;

    public function getDeclaringFunction(): ?string;
}
