<?php

namespace Amp\Injector;

use Amp\Injector\Provider\ObjectProvider;
use PHPUnit\Framework\TestCase;
use ProxyManager\Factory\LazyLoadingValueHolderFactory;
use ProxyManager\Proxy\LazyLoadingInterface;

class ProxyTest extends TestCase
{
    private static function proxy(ObjectProvider $provider): Provider
    {
        return new class ($provider) implements Provider {
            public function __construct(private ObjectProvider $provider)
            {
            }

            public function get(Context $context): object
            {
                return (new LazyLoadingValueHolderFactory)->createProxy(
                    $this->provider->getType(),
                    function (&$object, $proxy, $method, $parameters, &$initializer) use ($context) {
                        $object = $this->provider->get($context);
                        $initializer = null;
                    }
                );
            }

            public function getType(): ?string
            {
                return $this->provider->getType();
            }

            public function getDependencies(Context $context): array
            {
                return [$this->provider];
            }
        };
    }

    public function testInstanceReturnedFromProxy(): void
    {
        $contextBuilder = new ContextBuilder;
        $contextBuilder->add('test', self::proxy(autowire(TestDependency::class)));

        $class = $contextBuilder->build()->getType(TestDependency::class);

        self::assertInstanceOf(TestDependency::class, $class);
        self::assertInstanceOf(LazyLoadingInterface::class, $class);
        self::assertSame('testVal', $class->testProp);
    }

    public function testMakeInstanceInjectsSimpleConcreteDependencyProxy(): void
    {
        $contextBuilder = new ContextBuilder;
        $contextBuilder->add('test', self::proxy(autowire(TestDependency::class)));
        $contextBuilder->add('parent', autowire(TestNeedsDep::class));

        $needDep = $contextBuilder->build()->getType(TestNeedsDep::class);

        self::assertInstanceOf(TestNeedsDep::class, $needDep);
    }

    public function testShareInstanceProxy(): void
    {
        $contextBuilder = new ContextBuilder;
        $contextBuilder->add('test', singleton(self::proxy(autowire(TestDependency::class))));
        $context = $contextBuilder->build();

        $object1 = $context->getType(TestDependency::class);
        $object2 = $context->getType(TestDependency::class);

        self::assertSame($object1, $object2);
    }

    public function testProxyDefinition(): void
    {
        $contextBuilder = new ContextBuilder;
        $contextBuilder->add('test', self::proxy(autowire(NoTypehintNoDefaultConstructorClass::class, arguments()->name('arg', value(42)))));
        $contextBuilder->add('dependency', autowire(TestDependency::class));

        $object = $contextBuilder->build()->getType(NoTypehintNoDefaultConstructorClass::class);

        self::assertSame(42, $object->testParam);
    }
}
