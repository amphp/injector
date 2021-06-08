<?php

namespace Amp\Injector;

use Amp\Injector\Meta\Type;
use PHPUnit\Framework\TestCase;
use ProxyManager\Factory\LazyLoadingValueHolderFactory;
use ProxyManager\Proxy\LazyLoadingInterface;

class ProxyTest extends TestCase
{
    public function testInstanceReturnedFromProxy(): void
    {
        $definitions = (new Definitions)->with(self::proxy(TestDependency::class, object(TestDependency::class)));

        $object = factory(fn (TestDependency $instance) => $instance)->build(new Injector(automaticTypes($definitions)))->get(new ProviderContext);

        self::assertInstanceOf(TestDependency::class, $object);
        self::assertInstanceOf(LazyLoadingInterface::class, $object);
        self::assertSame('testVal', $object->testProp);
    }

    private static function proxy(string $class, Definition $definition): Definition
    {
        return new class($class, $definition) implements Definition {
            public function __construct(private string $class, private Definition $definition)
            {
            }

            public function getType(): Type
            {
                return new Type($this->class);
            }

            public function getAttribute(string $attribute): ?object
            {
                return $this->definition->getAttribute($attribute);
            }

            public function build(Injector $injector): Provider
            {
                $factory = new LazyLoadingValueHolderFactory;

                return factory(fn (ProviderContext $context) => $factory->createProxy(
                    $this->class,
                    function (&$object, $proxy, $method, $parameters, &$initializer) use ($injector, $context) {
                        $object = $this->definition->build($injector)->get($context);
                        $initializer = null;
                    }
                ))->build($injector);
            }
        };
    }

    public function testMakeInstanceInjectsSimpleConcreteDependencyProxy(): void
    {
        $definitions = (new Definitions)
            ->with(self::proxy(TestDependency::class, object(TestDependency::class)))
            ->with(object(TestNeedsDep::class));

        $needDep = factory(fn (TestNeedsDep $instance) => $instance)->build(new Injector(automaticTypes($definitions)))->get(new ProviderContext);

        self::assertInstanceOf(TestNeedsDep::class, $needDep);
    }

    public function testSingletonInstanceProxy(): void
    {
        $definitions = (new Definitions)
            ->with(singleton(self::proxy(TestDependency::class, object(TestDependency::class))));

        $injector = new Injector(automaticTypes($definitions));

        $object1 = factory(fn (TestDependency $instance) => $instance)->build($injector)->get(new ProviderContext);
        $object2 = factory(fn (TestDependency $instance) => $instance)->build($injector)->get(new ProviderContext);

        self::assertSame($object1, $object2);
    }

    public function testProxyDefinition(): void
    {
        $definitions = (new Definitions)
            ->with(self::proxy(NoTypehintNoDefaultConstructorClass::class, object(NoTypehintNoDefaultConstructorClass::class, arguments()->with(names()->with('arg', value(42))))))
            ->with(object(TestDependency::class));

        $injector = new Injector(automaticTypes($definitions));

        $object = factory(fn (NoTypehintNoDefaultConstructorClass $instance) => $instance)->build($injector)->get(new ProviderContext);

        self::assertSame(42, $object->testParam);
    }
}
