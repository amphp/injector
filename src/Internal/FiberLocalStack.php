<?php

namespace Amp\Injector\Internal;

use Kelunik\FiberLocal\FiberLocal;

/** @internal */
final class FiberLocalStack
{
    private FiberLocal $stack;

    public function __construct()
    {
        $this->stack = FiberLocal::withInitial(static fn() => []);
    }

    public function push(mixed $value): void
    {
        $stack = $this->stack->get();
        $stack[] = $value;

        $this->stack->set($stack);
    }

    public function pop(): mixed
    {
        $stack = $this->stack->get();

        if (!$stack) {
            return null;
        }

        $lastKey = array_key_last($stack);
        $lastValue = $stack[$lastKey];

        unset ($stack[$lastValue]);

        $this->stack->set($stack);

        return $lastValue;
    }

    public function toArray(): array
    {
        return $this->stack->get();
    }
}