<?php

namespace Amp\Injector;

use Amp\Injector\Meta\Parameter;
use PHPUnit\Framework\TestCase;

class LoggingProvider implements Provider, Lifecycle
{
    public function __construct(private string $id, private array $dependencies)
    {
    }

    public function has(Container $context, Parameter $parameter): bool
    {
        return true;
    }

    public function unwrap(): ?Provider
    {
        return null;
    }

    public function getDependencies(): array
    {
        return \array_map(fn ($id) => $context->getProvider($id), $this->dependencies);
    }

    public function start(): void
    {
        $this->get(new ProviderContext);
    }

    public function get(ProviderContext $context): mixed
    {
        print $this->id . ' ';

        return null;
    }

    public function stop(): void
    {
        print '2:' . $this->id . ' ';
    }
}

class LifecycleTest extends TestCase
{
    private Definitions $contextBuilder;

    public function testSimple()
    {
        $this->givenDependencies([
            'a' => ['b', 'c'],
            'b' => ['c'],
            'c' => [],
        ]);

        $this->expectOutputString('c b a 1:c 1:b 1:a ready 2:a 2:b 2:c ');

        $this->executeLifecycle();
    }

    private function givenDependencies(array $dependencies)
    {
        foreach ($dependencies as $id => $dependency) {
            $this->contextBuilder->add(new LoggingProvider($id, $dependency), $id);
        }
    }

    private function executeLifecycle()
    {
        $context = $this->contextBuilder->build();
        $context->start();
        print 'ready ';
        $context->stop();
    }

    public function testCircular()
    {
        $this->contextBuilder->add(singleton(new LoggingProvider('a', ['b'])), 'a');
        $this->contextBuilder->add(singleton(new LoggingProvider('b', []))->onStart(function ($b, $context) {
            print 'b+ ';
            $context->get('a');
        }), 'b');

        $this->expectOutputString('0:b b 0:a a 1:b b+ 1:a ready 2:a 2:b ');

        $this->executeLifecycle();
    }

    public function testOutOfOrder()
    {
        $this->givenDependencies([
            'b' => ['c'],
            'a' => ['b', 'c'],
            'c' => [],
            'd' => ['a'],
        ]);

        $this->expectOutputString('0:c 0:b 0:a 0:d 1:c 1:b 1:a 1:d ready 2:d 2:a 2:b 2:c ');

        $this->executeLifecycle();
    }

    protected function setUp(): void
    {
        $this->contextBuilder = new Definitions;
    }
}
