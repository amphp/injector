<?php

namespace Amp\Injector;

use Amp\Injector\Definition\ProviderDefinition;
use PHPUnit\Framework\TestCase;

class LoggingProvider implements Provider, Lifecycle
{
    public function __construct(private string $id, private array $dependencies)
    {
    }

    public function unwrap(): ?Provider
    {
        return null;
    }

    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function start(): void
    {
        print 'start:' . $this->id . ' ';

        $this->get(new ProviderContext);
    }

    public function get(ProviderContext $context): mixed
    {
        print $this->id . ' ';

        return null;
    }

    public function stop(): void
    {
        print 'stop:' . $this->id . ' ';
    }
}

class LifecycleTest extends TestCase
{
    public function testSimple()
    {
        $c = new LoggingProvider('c', []);
        $b = new LoggingProvider('b', [$c]);
        $a = new LoggingProvider('a', [$b, $c]);

        $definitions = (new Definitions)
            ->with(new ProviderDefinition($a))
            ->with(new ProviderDefinition($b))
            ->with(new ProviderDefinition($c));

        $injector = new Injector($definitions, any());

        $this->expectOutputString('start:c c start:b b start:a a ready stop:a stop:b stop:c ');

        $this->executeLifecycle($injector);
    }

    private function executeLifecycle(Injector $injector)
    {
        $application = new Application($injector);
        $application->start();
        print 'ready ';
        $application->stop();
    }

    public function testOutOfOrder()
    {
        $c = new LoggingProvider('c', []);
        $b = new LoggingProvider('b', [$c]);
        $a = new LoggingProvider('a', [$b, $c]);
        $d = new LoggingProvider('d', [$a]);

        $definitions = (new Definitions)
            ->with(new ProviderDefinition($a))
            ->with(new ProviderDefinition($b))
            ->with(new ProviderDefinition($c))
            ->with(new ProviderDefinition($d));

        $injector = new Injector($definitions, any());

        $this->expectOutputString('start:c c start:b b start:a a start:d d ready stop:d stop:a stop:b stop:c ');

        $this->executeLifecycle($injector);
    }
}
