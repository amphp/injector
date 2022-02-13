<?php

namespace Amp\Injector;

use Psr\Container\ContainerInterface;

/**
 * A container is responsible for (possibly indirectly) holding references to all its scoped entries.
 *
 * Entries might be application or request scoped. It does not old references to unscoped entries, i.e. entries
 * that are always recreated, so called prototypes.
 */
interface Container extends ContainerInterface, \IteratorAggregate
{
    public function get(string $id): mixed;

    public function has(string $id): bool;

    /** @return iterable<Provider> */
    public function getIterator(): \Traversable;

    public function getProvider(string $id): Provider;
}
