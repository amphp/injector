<?php

namespace Amp\Injector;

use Amp\Injector\Meta\Parameter;
use Amp\Injector\Provider\FactoryProvider;
use PHPUnit\Framework\TestCase;
use ProxyManager\Factory\LazyLoadingValueHolderFactory;
use ProxyManager\Proxy\LazyLoadingInterface;

class ProxyTest extends TestCase
{
    private static function proxy(FactoryProvider $provider): Provider
    {
        return new class($provider) implements Provider {
            public function __construct(private FactoryProvider $provider)
            {
            }

            public function has(Container $context, Parameter $parameter): bool
            {
                return true;
            }

            public function get(ProviderContext $context): object
            {
                return (new LazyLoadingValueHolderFactory)->createProxy(
                    $this->provider->getType(),// TODO type
                    function (&$object, $proxy, $method, $parameters, &$initializer) use ($context, $parameter) {
                        $object = $this->provider->get($parameter);
                        $initializer = null;
                    }
                );
            }

            public function unwrap(): ?Provider
            {
                return $this->provider;
            }

            public function getDependencies(): array
            {
                return [$this->provider];
            }
        };
    }

    public function testInstanceReturnedFromProxy(): void
    {
        $contextBuilder = new Definitions;
        $contextBuilder->add(self::proxy(object(TestDependency::class)), 'test');

        $class = $contextBuilder->build()->getType(TestDependency::class);

        self::assertInstanceOf(TestDependency::class, $class);
        self::assertInstanceOf(LazyLoadingInterface::class, $class);
        self::assertSame('testVal', $class->testProp);
    }

    public function testMakeInstanceInjectsSimpleConcreteDependencyProxy(): void
    {
        $contextBuilder = new Definitions;
        $contextBuilder->add(self::proxy(object(TestDependency::class)), 'test');
        $contextBuilder->add(object(TestNeedsDep::class), 'parent');

        $needDep = $contextBuilder->build()->getType(TestNeedsDep::class);

        self::assertInstanceOf(TestNeedsDep::class, $needDep);
    }

    public function testShareInstanceProxy(): void
    {
        $contextBuilder = new Definitions;
        $contextBuilder->add(singleton(self::proxy(object(TestDependency::class))), 'test');
        $context = $contextBuilder->build();
        $context->start();

        $object1 = $context->getType(TestDependency::class);
        $object2 = $context->getType(TestDependency::class);

        self::assertSame($object1, $object2);
    }

    public function testProxyDefinition(): void
    {
        $contextBuilder = new Definitions;
        $contextBuilder->add(self::proxy(object(NoTypehintNoDefaultConstructorClass::class, arguments()->name('arg', value(42)))), 'test');
        $contextBuilder->add(object(TestDependency::class), 'dependency');

        $object = $contextBuilder->build()->getType(NoTypehintNoDefaultConstructorClass::class);

        self::assertSame(42, $object->testParam);
    }
}
