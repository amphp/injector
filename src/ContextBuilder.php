<?php

namespace Amp\Injector;

use Amp\Injector\ImplementationResolver\AutomaticImplementationResolver;
use Amp\Injector\ImplementationResolver\NullImplementationResolver;
use Amp\Injector\ImplementationResolver\PrimaryImplementationResolver;

final class ContextBuilder
{
    private RootContext $context;
    private AutomaticImplementationResolver $automaticImplementationResolver;
    private PrimaryImplementationResolver $primaryImplementationResolver;

    public function __construct()
    {
        $this->automaticImplementationResolver = new AutomaticImplementationResolver;
        $this->primaryImplementationResolver = new PrimaryImplementationResolver(new NullImplementationResolver);
        $this->context = new RootContext;
    }

    /**
     * @throws InjectionException
     */
    public function add(string $id, Provider $provider): void
    {
        $type = $provider->getType();

        $this->context = $this->context->with($id, $provider);

        if ($type !== null) {
            $this->automaticImplementationResolver = $this->automaticImplementationResolver->with($type, $id);
        }
    }

    public function primary(string $class, string $id): void
    {
        $this->primaryImplementationResolver = $this->primaryImplementationResolver->with($class, $id);
    }

    public function build(): ApplicationContext
    {
        $implementationResolver = $this->primaryImplementationResolver->withFallback($this->automaticImplementationResolver);

        return $this->context->withImplementationResolver($implementationResolver);
    }
}