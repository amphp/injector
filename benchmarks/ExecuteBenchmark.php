<?php

namespace Amp\Injector\Benchmarks;

use Amp\Injector\Application;
use Amp\Injector\Injector;
use Amp\Injector\Noop;
use Amp\Injector\Provider;
use Amp\Injector\ProviderContext;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\OutputTimeUnit;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use function Amp\Injector\arguments;
use function Amp\Injector\automaticTypes;
use function Amp\Injector\definitions;
use function Amp\Injector\factory;
use function Amp\Injector\names;
use function Amp\Injector\object;
use function Amp\Injector\value;

#[BeforeMethods('init')]
#[Iterations(3)]
#[Revs(10000)]
#[Warmup(1)]
#[OutputTimeUnit('milliseconds', precision: 5)]
class ExecuteBenchmark
{
    private Injector $injector;
    private Application $application;

    private Noop $noop;
    private Provider $closureCached;
    private Provider $namedParamsCached;

    public function init(): void
    {
        $definitions = definitions()->with(object(\stdClass::class));
        $this->injector = new Injector(automaticTypes($definitions));
        $this->application = new Application($this->injector, $definitions);

        $this->noop = new Noop;
        $this->closureCached = factory(function () {
            // call-target, intentionally left empty
        })->build($this->injector);

        $this->namedParamsCached = factory(\Closure::fromCallable([$this->noop, 'namedNoop']), arguments(names(['name' => value('foo')])))->build($this->injector);
    }

    public function benchNativeInvokeClosure(): void
    {
        (function () {
            // call-target, intentionally left empty
        })();
    }

    public function benchNativeInvokeMethod(): void
    {
        $method = 'noop';
        $this->noop->$method();
    }

    public function benchInvokeClosure(): void
    {
        $this->application->invoke(factory(function () {
            // call-target, intentionally left empty
        }));
    }

    public function benchInvokeClosureCached(): void
    {
        $this->closureCached->get(new ProviderContext);
    }

    public function benchInvokeMethod(): void
    {
        $this->application->invoke(factory(\Closure::fromCallable([$this->noop, 'noop'])));
    }

    public function benchInvokeWithNamedParameters(): void
    {
        $this->application->invoke(factory(\Closure::fromCallable([$this->noop, 'namedNoop']), arguments(names(['name' => value('foo')]))));
    }

    public function benchInvokeWithNamedParametersCached(): void
    {
        $this->namedParamsCached->get(new ProviderContext);
    }
}
