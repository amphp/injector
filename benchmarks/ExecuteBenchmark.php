<?php

namespace Amp\Injector\Benchmarks;

use Amp\Injector\Injector;
use Amp\Injector\Noop;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\OutputTimeUnit;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

#[BeforeMethods('init')]
#[Iterations(3)]
#[Revs(1000000)]
#[Warmup(1)]
#[OutputTimeUnit('milliseconds', precision: 5)]
class ExecuteBenchmark
{
    private Injector $injector;
    private Noop $noop;

    public function init(): void
    {
        $this->injector = new Injector;
        $this->noop = new Noop;
    }

    public function benchNativeInvokeClosure(): void
    {
        \call_user_func(function () {
            // call-target, intentionally left empty
        });
    }

    public function benchNativeInvokeMethod(): void
    {
        \call_user_func([$this->noop, 'noop']);
    }

    public function benchInvokeClosure(): void
    {
        $this->injector->execute(function () {
            // call-target, intentionally left empty
        });
    }

    public function benchInvokeMethod(): void
    {
        $this->injector->execute([$this->noop, 'noop']);
    }

    public function benchInvokeWithNamedParameters(): void
    {
        $this->injector->execute([$this->noop, 'namedNoop'], [':name' => 'foo']);
    }
}
