<?php

namespace Amp\Injector;

use PHPUnit\Framework\TestCase;

class InjectorTest extends TestCase
{
    public function testInjectsSimpleConcreteDependency(): void
    {
        self::assertEquals(
            new TestNeedsDep(new TestDependency),
            $this->invoke(object(TestNeedsDep::class, arguments()->with(automaticTypes((new Definitions)->with(object(TestDependency::class))))))
        );
    }

    private function invoke(Definition $definition, ?Definitions $definitions = null): mixed
    {
        $definitions ??= new Definitions;

        return $definition->build(new Injector(automaticTypes($definitions)))->get(new ProviderContext);
    }

    public function testReturnsNewInstanceIfClassHasNoConstructor(): void
    {
        self::assertEquals(new TestNoConstructor, $this->invoke(object(TestNoConstructor::class)));
    }

    public function testMakeInstanceReturnsAliasInstanceOnNonConcreteTypehint(): void
    {
        self::assertEquals(new DepImplementation, $this->invoke(factory(fn (DepInterface $instance) => $instance, arguments()->with(automaticTypes((new Definitions)->with(object(DepImplementation::class)))))));
    }

    public function testThrowsExceptionOnInterfaceWithoutDefinition(): void
    {
        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Could not find a suitable definition for parameter #0 ($instance)');

        $this->invoke(factory(fn (DepInterface $instance) => $instance));
    }

    public function testThrowsExceptionOnNonConcreteCtorParamWithoutImplementation(): void
    {
        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Could not find a suitable definition for parameter #0 ($dep)');

        $this->invoke(factory(fn (RequiresInterface $instance) => $instance, arguments()->with(automaticTypes((new Definitions)->with(object(RequiresInterface::class))))));
    }

    public function testBuildsNonConcreteConstructorParameter(): void
    {
        $definitions = (new Definitions)->with(object(RequiresInterface::class))->with(object(DepImplementation::class));

        $object = $this->invoke(factory(fn (RequiresInterface $instance) => $instance), $definitions);

        self::assertInstanceOf(RequiresInterface::class, $object);
    }

    public function testPassesDefaultValueToConstructorParameterIfNoValueCanBeDetermined(): void
    {
        $object = $this->invoke(factory(fn (ProvTestNoDefinitionNullDefaultClass $instance) => $instance, arguments()->with(automaticTypes((new Definitions())->with(object(ProvTestNoDefinitionNullDefaultClass::class))))));

        self::assertEquals(new ProvTestNoDefinitionNullDefaultClass, $object);
        self::assertNull($object->arg);
    }

    public function testReturnsSingletonInstance(): void
    {
        $definitions = (new Definitions)->with(object(DepImplementation::class))->with(singleton(object(RequiresInterface::class)));

        $application = new Application(new Injector(automaticTypes($definitions)), $definitions);

        $object1 = factory(fn (RequiresInterface $instance) => $instance)->build($application->getInjector())->get(new ProviderContext);

        self::assertEquals('something', $object1->testDep->testProp);
        $object1->testDep->testProp = 'something else';

        $object2 = factory(fn (RequiresInterface $instance) => $instance)->build($application->getInjector())->get(new ProviderContext);
        self::assertEquals('something else', $object2->testDep->testProp);
    }

    public function testThrowsExceptionOnParameterWithoutDefinitionOrDefault(): void
    {
        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Could not find a suitable definition for parameter #0 ($val)');

        $this->invoke(factory(fn (InjectorTestCtorParamWithNoTypehintOrDefault $instance) => $instance), (new Definitions)->with(object(InjectorTestCtorParamWithNoTypehintOrDefault::class)));
    }

    public function testTypelessDefineForDependency(): void
    {
        $definitions = (new Definitions)->with(object(TypelessParameterDependency::class, arguments()->with(names()->with('thumbnailSize', value(128)))))->with(object(RequiresDependencyWithTypelessParameters::class, ));

        $object = $this->invoke(factory(fn (RequiresDependencyWithTypelessParameters $instance) => $instance), $definitions);

        self::assertEquals(128, $object->getThumbnailSize());
    }

    public function testMakeInstanceInjectsRawParametersDirectly(): void
    {
        $object = $this->invoke(object(InjectorTestRawCtorParams::class, arguments()->with(names()
            ->with('string', value('string'))
            ->with('obj', value(new \StdClass))
            ->with('int', value(42))
            ->with('array', value([]))
            ->with('float', value(9.3))
            ->with('bool', value(true))
            ->with('null', value(null)))));

        self::assertIsString($object->string);
        self::assertInstanceOf('StdClass', $object->obj);
        self::assertIsInt($object->int);
        self::assertIsArray($object->array);
        self::assertIsFloat($object->float);
        self::assertIsBool($object->bool);
        self::assertNull($object->null);
    }

    public function testMakeInstanceThrowsExceptionWhenDelegateDoes(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('test exception');

        $this->invoke(factory(fn () => throw new \Exception('test exception')));
    }

    public function provideExecutionExpectations(): array
    {
        $return = [];

        // 0 -------------------------------------------------------------------------------------->

        $toInvoke = [ExecuteClassNoDeps::class, 'execute'];
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];

        // 1 -------------------------------------------------------------------------------------->

        $toInvoke = [new ExecuteClassNoDeps, 'execute'];
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];

        // 2 -------------------------------------------------------------------------------------->

        $toInvoke = [ExecuteClassDeps::class, 'execute'];
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];

        // 3 -------------------------------------------------------------------------------------->

        $toInvoke = [new ExecuteClassDeps(new TestDependency), 'execute'];
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];

        // 4 -------------------------------------------------------------------------------------->

        $toInvoke = [ExecuteClassDepsWithMethodDeps::class, 'execute'];
        $args = [':arg' => 9382];
        $expectedResult = 9382;
        $return[] = [$toInvoke, $args, $expectedResult];

        // 5 -------------------------------------------------------------------------------------->

        $toInvoke = [ExecuteClassStaticMethod::class, 'execute'];
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];

        // 6 -------------------------------------------------------------------------------------->

        $toInvoke = [new ExecuteClassStaticMethod, 'execute'];
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];

        // 7 -------------------------------------------------------------------------------------->

        $toInvoke = 'Amp\Injector\ExecuteClassStaticMethod::execute';
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];

        // 8 -------------------------------------------------------------------------------------->

        $toInvoke = [ExecuteClassRelativeStaticMethod::class, 'parent::execute'];
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];

        // 9 -------------------------------------------------------------------------------------->

        $toInvoke = 'Amp\Injector\testExecuteFunction';
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];

        // 10 ------------------------------------------------------------------------------------->

        $toInvoke = function () {
            return 42;
        };
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];

        // 11 ------------------------------------------------------------------------------------->

        $toInvoke = new ExecuteClassInvokable;
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];

        // 12 ------------------------------------------------------------------------------------->

        $toInvoke = ExecuteClassInvokable::class;
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];

        // 13 ------------------------------------------------------------------------------------->

        $toInvoke = 'Amp\Injector\ExecuteClassNoDeps::execute';
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];

        // 14 ------------------------------------------------------------------------------------->

        $toInvoke = 'Amp\Injector\ExecuteClassDeps::execute';
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];

        // 15 ------------------------------------------------------------------------------------->

        $toInvoke = 'Amp\Injector\ExecuteClassStaticMethod::execute';
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];

        // 16 ------------------------------------------------------------------------------------->

        $toInvoke = 'Amp\Injector\ExecuteClassRelativeStaticMethod::parent::execute';
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];

        // 17 ------------------------------------------------------------------------------------->

        $toInvoke = 'Amp\Injector\testExecuteFunctionWithArg';
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];

        // 18 ------------------------------------------------------------------------------------->

        $toInvoke = function () {
            return 42;
        };
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];

        if (PHP_VERSION_ID > 50400) {
            // 19 ------------------------------------------------------------------------------------->

            $object = new ReturnsCallable('new value');
            $args = [];
            $toInvoke = $object->getCallable();
            $expectedResult = 'new value';
            $return[] = [$toInvoke, $args, $expectedResult];
        }
        // x -------------------------------------------------------------------------------------->

        return $return;
    }

    public function provideCyclicDependencies(): array
    {
        return [
            RecursiveClassA::class => [RecursiveClassA::class],
            RecursiveClassB::class => [RecursiveClassB::class],
            RecursiveClassC::class => [RecursiveClassC::class],
            RecursiveClass1::class => [RecursiveClass1::class],
            RecursiveClass2::class => [RecursiveClass2::class],
            DependsOnCyclic::class => [DependsOnCyclic::class],
        ];
    }

    /**
     * @dataProvider          provideCyclicDependencies
     */
    public function testCyclicDependencies($class): void
    {
        $this->expectException(InjectionException::class);

        $this->invoke(object($class));
    }

    public function testNonConcreteDependencyWithDefault(): void
    {
        $object = $this->invoke(object(NonConcreteDependencyWithDefaultValue::class));

        self::assertInstanceOf(NonConcreteDependencyWithDefaultValue::class, $object);
        self::assertNull($object->interface);
    }

    public function testNonConcreteDependencyWithDefaultValueAndDefinition(): void
    {
        $object = $this->invoke(object(NonConcreteDependencyWithDefaultValue::class, arguments()->with(automaticTypes((new Definitions)->with(object(ImplementsInterface::class))))));

        self::assertInstanceOf(NonConcreteDependencyWithDefaultValue::class, $object);
        self::assertInstanceOf(ImplementsInterface::class, $object->interface);
    }

    public function testAppropriateExceptionThrownOnNonPublicConstructor(): void
    {
        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Cannot instantiate protected/private constructor in class Amp\Injector\HasNonPublicConstructor');

        $this->invoke(object(HasNonPublicConstructor::class));
    }

    public function testAppropriateExceptionThrownOnNonPublicConstructorWithArgs(): void
    {
        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Cannot instantiate protected/private constructor in class Amp\Injector\HasNonPublicConstructorWithArgs');

        $this->invoke(object(HasNonPublicConstructorWithArgs::class));
    }

    public function testWithLeadingBackslash(): void
    {
        $object = $this->invoke(object('\\' . SimpleNoTypehintClass::class, arguments()->with(names()->with('arg', value('tested')))));

        self::assertEquals('tested', $object->testParam);
    }

    public function testDelegationFunction(): void
    {
        $object = $this->invoke(factory(\Closure::fromCallable('Amp\Injector\createTestDelegationSimple')));

        self::assertInstanceOf(TestDelegationSimple::class, $object);
        self::assertTrue($object->delegateCalled);
    }

    public function testDelegationDependency(): void
    {
        $object = $this->invoke(factory(\Closure::fromCallable('Amp\Injector\createTestDelegationDependency'), arguments()->with(types()->with(TestDelegationSimple::class, factory(\Closure::fromCallable('Amp\Injector\createTestDelegationSimple'))))));

        self::assertInstanceOf(TestDelegationDependency::class, $object);
        self::assertTrue($object->delegateCalled);
    }

    /**
     * Test coverage for delegate closures that are defined outside of a class.
     */
    public function testDelegateClosure(): void
    {
        $delegateClosure = getDelegateClosureInGlobalScope();

        $this->invoke(factory($delegateClosure));

        $this->expectNotToPerformAssertions();
    }

    public function testDelegationDoesntMakeObject(): void
    {
        $delegate = function () {
            return new \stdClass;
        };

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Argument #1 ($instance) must be of type Amp\Injector\TestDependency, stdClass given');

        $this->invoke(factory(fn (TestDependency $instance) => $instance, arguments()->with(names()->with('instance', factory($delegate)))));
    }

    public function testChildWithoutConstructorWorks(): void
    {
        $child = $this->invoke(object(ChildWithoutConstructor::class, arguments()->with(names()->with('foo', value('child')))));
        self::assertEquals('child', $child->foo);

        $parent = $this->invoke(object(ParentWithConstructor::class, arguments()->with(names()->with('foo', value('parent')))));
        self::assertEquals('parent', $parent->foo);
    }

    public function testChildWithoutConstructorMissingParam(): void
    {
        $this->invoke(object(ParentWithConstructor::class, arguments()->with(names()->with('foo', value('parent')))));

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Could not find a suitable definition for parameter #0 ($foo) in Amp\Injector\ChildWithoutConstructor::__construct()');

        $this->invoke(object(ChildWithoutConstructor::class, arguments()->with(names())));
    }

    public function testThatExceptionInConstructorDoesntCauseCyclicDependencyException(): void
    {
        // TODO: this tests with different providers currently
        try {
            $this->invoke(object(ThrowsExceptionInConstructor::class));
        } catch (\Exception) {
            // ignore
        }

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Exception in constructor');

        $this->invoke(object(ThrowsExceptionInConstructor::class));
    }
}
