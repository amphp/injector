<?php

namespace Amp\Injector\Meta;

use function Amp\Injector\Internal\normalizeClass;

final class Type
{
    private array $types;

    public function __construct(string ...$types)
    {
        $this->types = $types;
    }

    public static function fromReflection(?\ReflectionType $reflectionType): ?self
    {
        if (!$reflectionType) {
            return null;
        }

        $types = [];

        if ($reflectionType instanceof \ReflectionUnionType) {
            foreach ($reflectionType->getTypes() as $type) {
                $types[normalizeClass($type->getName())] = $type->getName();

                if ($type->allowsNull()) {
                    $types['null'] = 'null';
                }
            }
        }

        if ($reflectionType instanceof \ReflectionNamedType) {
            $types[normalizeClass($reflectionType->getName())] = $reflectionType->getName();

            if ($reflectionType->allowsNull()) {
                $types['null'] = 'null';
            }
        }

        return new self(...\array_values($types));
    }

    public function getTypes(): array
    {
        return $this->types;
    }

    public function isNullable(): bool
    {
        return \in_array('null', $this->types, true);
    }
}
