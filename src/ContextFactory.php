<?php

namespace Amp\Injector;

use Amp\Injector\Internal\CachingReflector;
use Amp\Injector\Internal\StandardReflector;
use Amp\Injector\Provider\AutomaticProvider;
use Amp\Injector\Provider\SingletonProvider;
use Amp\Injector\Provider\ValueProvider;
use Amp\Injector\TypeRules\AutomaticTypeRules;
use Amp\Injector\TypeRules\PrimaryTypeRules;

// TODO: Currently this is half-immutable, which is bad
final class ContextFactory
{
    private RootContext $context;
    private AutomaticTypeRules $autoRules;
    private PrimaryTypeRules $primaryRules;

    public function __construct()
    {
        $this->autoRules = new AutomaticTypeRules(new CachingReflector(new StandardReflector));
        $this->primaryRules = new PrimaryTypeRules($this->autoRules);
        $this->context = new RootContext($this->primaryRules);
    }

    public function add(string $id, string $class, Provider $provider): void
    {
        $this->context = $this->context->with($id, $provider);
        $this->autoRules->add($class, $id);
    }

    public function singleton(string $id, string $class, ?ArgumentRules $arguments = null): void
    {
        $this->context = $this->context->with($id, new SingletonProvider(new AutomaticProvider($class, $arguments ?? arguments())));
        $this->autoRules->add($class, $id);
    }

    public function prototype(string $id, string $class, ?ArgumentRules $arguments = null): void
    {
        $this->context = $this->context->with($id, new AutomaticProvider($class, $arguments ?? arguments()));
        $this->autoRules->add($class, $id);
    }

    public function value(string $id, mixed $value): void
    {
        $this->context = $this->context->with($id, new ValueProvider($value));

        if (\is_object($value)) {
            $this->autoRules->add(\get_class($value), $id);
        }
    }

    public function primary(string $class, string $id): void
    {
        $this->primaryRules->set($class, $id);
    }

    public function build(): Context
    {
        return $this->context;
    }
}