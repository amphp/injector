<?php

namespace Amp\Injector;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

interface Provider
{
    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function get(Context $context): mixed;

    /**
     * @return string|null Class name or null if not available or shouldn't be autowired based on types.
     */
    public function getType(): ?string;

    /**
     * @param Context $context Implementations can assume the context to be complete for making decisions.
     * @return array An array of providers which should be initialized first.
     */
    public function getDependencies(Context $context): array;
}