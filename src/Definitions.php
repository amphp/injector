<?php

namespace Amp\Injector;

final class Definitions implements \IteratorAggregate
{
    private static int $nextId = 0;

    /** @var Definition[] */
    private array $definitions = [];

    public function with(Definition $definition, ?string $id = null): self
    {
        $clone = clone $this;
        $clone->definitions[$id ?? $clone->generateId($definition)] = $definition;

        return $clone;
    }

    private function generateId(Definition $definition): string
    {
        $type = $definition->getType();

        return '#' . self::$nextId++ . ($type !== null ? '-' . \implode('-', $type->getTypes()) : '');
    }

    public function get(string $id): ?Definition
    {
        return $this->definitions[$id] ?? null;
    }

    /**
     * @return iterable<Definition>
     */
    public function getIterator(): iterable
    {
        foreach ($this->definitions as $id => $definition) {
            yield $id => $definition;
        }
    }
}
