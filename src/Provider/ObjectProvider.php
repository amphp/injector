<?php

namespace Amp\Injector\Provider;

use Amp\Injector\Context;
use Amp\Injector\InjectionException;
use Amp\Injector\Provider;

final class ObjectProvider implements Provider
{
    private string $class;
    private array $args;

    public function __construct(string $class, array $args)
    {
        $this->class = $class;
        $this->args = $args;
    }

    public function get(Context $context): object
    {
        try {
            $args = [];

            foreach ($this->args as $arg) {
                $args[] = $arg->get($context);
            }

            return new $this->class(...$args);
        } catch (\Exception $e) {
            throw new InjectionException(
                \sprintf('Could not create %s: %s', $this->class, $e->getMessage()),
                $e
            );
        }
    }

    public function getType(): string
    {
        return $this->class;
    }

    public function getDependencies(Context $context): array
    {
        return $this->args;
    }
}