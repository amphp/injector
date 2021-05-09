<?php

namespace Amp\Injector\TypeRules;

use Amp\Injector\TypeRules;
use function Amp\Injector\Internal\normalizeClass;

// TODO: Make immutable?
final class PrimaryTypeRules implements TypeRules
{
    private TypeRules $fallback;

    /** @var string[] */
    private array $primaryIdentifiers = [];

    public function __construct(TypeRules $fallback)
    {
        $this->fallback = $fallback;
    }

    public function get(string $class): ?string
    {
        return $this->primaryIdentifiers[normalizeClass($class)] ?? $this->fallback->get($class);
    }

    public function set(string $class, string $id): void
    {
        $class = normalizeClass($class);

        $this->primaryIdentifiers[$class] = $id;
    }
}