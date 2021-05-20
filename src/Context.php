<?php

namespace Amp\Injector;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * A context is responsible for (possibly indirectly) holding references to all its scoped entries.
 *
 * Entries might be application or request scoped. It does not old references to unscoped entries, i.e. entries
 * that are always recreated, so called prototypes.
 *
 * Providers can make use of the container entries for autowiring purposes.
 */
interface Context extends ContainerInterface
{
    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function getType(string $class): object;

    public function hasType(string $class): bool;

    public function get(string $id): mixed;

    public function has(string $id): bool;

    public function getIds(): array;

    public function getProvider(string $id): Provider;

    public function getTypeProvider(string $class): Provider;

    public function getDependents(): array;
}