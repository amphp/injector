<?php

namespace Amp\Injector\ImplementationResolver;

use Amp\Injector\ImplementationResolver;
use function Amp\Injector\Internal\normalizeClass;

final class PrimaryImplementationResolver implements ImplementationResolver
{
    private ImplementationResolver $fallback;

    /** @var string[] */
    private array $primaryIdentifiers = [];

    public function __construct(ImplementationResolver $fallback)
    {
        $this->fallback = $fallback;
    }

    public function get(string $class): ?string
    {
        return $this->primaryIdentifiers[normalizeClass($class)] ?? $this->fallback->get($class);
    }

    public function with(string $class, string $id): self
    {
        $class = normalizeClass($class);

        $clone = clone $this;
        $clone->primaryIdentifiers[$class] = $id;

        return $clone;
    }

    public function withFallback(ImplementationResolver $fallback): self
    {
        $clone = clone $this;
        $clone->fallback = $fallback;

        return $clone;
    }
}