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

    public function start(Context $context): void
    {
        print 'start:' . $this->id . ' ';
    }

    public function stop(Context $context): void
    {
        print 'stop:' . $this->id . ' ';
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

        $this->expectOutputString('start:c start:b start:a ready stop:a stop:b stop:c ');

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
        $context->start();
        print 'ready ';
        $context->stop();
    }

    public function testOutOfOrder()
    {
        $this->givenDependencies([
            'b' => ['c'],
            'a' => ['b', 'c'],
            'c' => [],
            'd' => ['a'],
        ]);

        $this->expectOutputString('start:c start:b start:a start:d ready stop:d stop:a stop:b stop:c ');

        $this->executeLifecycle();
    }

    protected function setUp(): void
    {
        $this->contextBuilder = new ContextBuilder;
    }
}