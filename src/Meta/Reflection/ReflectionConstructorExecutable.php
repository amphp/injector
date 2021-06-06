<?php

namespace Amp\Injector\Meta\Reflection;

use Amp\Injector\InjectionException;
use Amp\Injector\Meta\Executable;
use Amp\Injector\Meta\Type;
use function Amp\Injector\Internal\getDefaultReflector;

final class ReflectionConstructorExecutable implements Executable
{
    private string $class;
    private ?\ReflectionMethod $constructor;

    /**
     * @throws InjectionException
     */
    public function __construct(string $class)
    {
        $this->class = $class;

        $reflector = getDefaultReflector();

        $class = $reflector->getClass($class);
        $this->constructor = $reflector->getConstructor($class->getName());

        if (!$class->isInstantiable()) {
            if ($this->constructor && !$this->constructor->isPublic()) {
                throw new InjectionException('Cannot instantiate protected/private constructor in class ' . $class->getName());
            } elseif (!$this->constructor) {
                throw new InjectionException(\sprintf(
                    'Injection definition required for %s %s',
                    $class->isInterface() ? 'interface' : 'abstract class',
                    $class->getName()
                ));
            }
        }
    }

    public function getParameters(): array
    {
        $parameters = [];

        if ($this->constructor) {
            foreach ($this->constructor->getParameters() as $parameter) {
                $parameters[] = new ReflectionFunctionParameter($parameter, $this);
            }
        }

        return $parameters;
    }

    public function getType(): ?Type
    {
        return new Type($this->class);
    }

    public function getAttribute(string $attribute): ?object
    {
        $attributes = $this->constructor->getAttributes($attribute);
        if (isset($attributes[0])) {
            return $attributes[0]->newInstance();
        }

        return null;
    }

    // TODO Rename to getClass
    public function getDeclaringClass(): ?string
    {
        return $this->class;
    }

    public function __invoke(...$args): mixed
    {
        return new $this->class(...$args);
    }

    public function __toString(): string
    {
        return $this->class . '::__construct()';
    }
}
