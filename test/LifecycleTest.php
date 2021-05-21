<?php

namespace Amp\Injector;

use PHPUnit\Framework\TestCase;

class LoggingProvider implements Provider, ProviderLifecycle
{
    public function __construct(private string $id, private array $dependencies)
    {
    }

    public function get(Context $context): mixed
    {
        print $this->id . ' ';

        return null;
    }

    public function getType(): ?string
    {
        return null;
    }

    public function getDependencies(Context $context): array
    {
        return array_map(fn($id) => $context->getProvider($id), $this->dependencies);
    }

    public function instantiate(Context $context): void
    {
        print '0:' . $this->id . ' ';
    }

    public function start(Context $context): void
    {
        print '1:' . $this->id . ' ';
    }

    public function stop(Context $context): void
    {
        print '2:' . $this->id . ' ';
    }
}

class LifecycleTest extends TestCase
{
    private ContextBuilder $contextBuilder;

    public function testSimple()
    {
        $this->givenDependencies([
            'a' => ['b', 'c'],
            'b' => ['c'],
            'c' => [],
        ]);

        $this->expectOutputString('0:c 0:b 0:a 1:c 1:b 1:a ready 2:a 2:b 2:c ');

        $this->executeLifecycle();
    }

    private function givenDependencies(array $dependencies)
    {
        foreach ($dependencies as $id => $dependency) {
            $this->contextBuilder->add($id, new LoggingProvider($id, $dependency));
        }
    }

    private function executeLifecycle()
    {
        $context = $this->contextBuilder->build();
        $context->instantiate();
        $context->start();
        print 'ready ';
        $context->stop();
    }

    public function testCircular()
    {
        $this->contextBuilder->add('a', singleton(new LoggingProvider('a', ['b'])));
        $this->contextBuilder->add('b', singleton(new LoggingProvider('b', []))->onStart(function ($b, $context) {
            print 'b+ ';
            $context->get('a');
        }));

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
        $this->contextBuilder = new ContextBuilder;
    }
}