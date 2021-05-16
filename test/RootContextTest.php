<?php

namespace Amp\Injector;

use Amp\Injector\Provider\DynamicProvider;
use Amp\Injector\Provider\TypeReference;
use Amp\Injector\Provider\ValueProvider;
use PHPUnit\Framework\TestCase;

class RootContextTest extends TestCase
{
    private ContextBuilder $builder;

    public function testMakeInstanceInjectsSimpleConcreteDependency(): void
    {
        $this->builder->add('test', autowire(TestNeedsDep::class));
        $this->builder->add('dependency', autowire(TestDependency::class));

        $instance = $this->whenGetType(TestNeedsDep::class);

        self::assertEquals(
            new TestNeedsDep(new TestDependency),
            $instance
        );
    }

    private function whenGetType(string $class): object
    {
        $context = $this->builder->build();

        return $context->getType($class);
    }

    public function testMakeInstanceReturnsNewInstanceIfClassHasNoConstructor(): void
    {
        $this->builder->add('test', autowire(TestNoConstructor::class));

        self::assertEquals(new TestNoConstructor, $this->whenGetType(TestNoConstructor::class));
    }

    public function testMakeInstanceReturnsAliasInstanceOnNonConcreteTypehint(): void
    {
        $this->builder->add('test', autowire(DepImplementation::class));

        self::assertEquals(new DepImplementation, $this->whenGetType(DepInterface::class));
    }

    public function testMakeInstanceThrowsExceptionOnInterfaceWithoutAlias(): void
    {
        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('No implementation found for type Amp\Injector\DepInterface');

        $this->whenGetType(DepInterface::class);
    }

    public function testMakeInstanceThrowsExceptionOnNonConcreteCtorParamWithoutImplementation(): void
    {
        $this->builder->add('test', autowire(RequiresInterface::class));

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('No implementation found for type Amp\Injector\DepInterface');

        $this->whenGetType(RequiresInterface::class);
    }

    public function testMakeInstanceBuildsNonConcreteCtorParamWithAlias(): void
    {
        $this->builder->add('impl', autowire(DepImplementation::class));
        $this->builder->add('reqInterface', autowire(RequiresInterface::class));

        $object = $this->whenGetType(RequiresInterface::class);

        self::assertInstanceOf(RequiresInterface::class, $object);
    }

    public function testMakeInstancePassesNullCtorParameterIfNoTypehintOrDefaultCanBeDetermined(): void
    {
        $this->builder->add('test', autowire(ProvTestNoDefinitionNullDefaultClass::class));

        $object = $this->whenGetType(ProvTestNoDefinitionNullDefaultClass::class);

        self::assertEquals(new ProvTestNoDefinitionNullDefaultClass, $object);
        self::assertNull($object->arg);
    }

    public function testMakeInstanceReturnsSharedInstanceIfAvailable(): void
    {
        $this->builder->add('depImpl', autowire(DepImplementation::class));
        $this->builder->add('reqInterface', singleton(autowire( RequiresInterface::class, arguments()->name('dep', new TypeReference(DepImplementation::class)))));

        $object1 = $this->whenGetType(RequiresInterface::class);

        self::assertEquals('something', $object1->testDep->testProp);
        $object1->testDep->testProp = 'something else';

        $object2 = $this->whenGetType(RequiresInterface::class);
        self::assertEquals('something else', $object2->testDep->testProp);
    }

    public function testMakeInstanceThrowsExceptionOnClassLoadFailure(): void
    {
        self::markTestSkipped('Currently directly throws');

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Could not make ClassThatDoesntExist: Class "ClassThatDoesntExist" does not exist');

        $this->whenGetType('ClassThatDoesntExist');
    }

    // public function testMakeInstanceUsesCustomDefinitionIfSpecified(): void
    // {
    //     $injector->define(TestNeedsDep::class, ['testDep' => TestDependency::class]);
    //     $injected = $this->whenGetType(TestNeedsDep::class, ['testDep' => TestDependency2::class]);
    //     self::assertEquals('testVal2', $injected->testDep->testProp);
    // }

    public function testMakeInstanceUsesReflectionForUnknownParamsInMultiBuildWithDeps(): void
    {
        $this->builder->add('a',  autowire(TestDependency::class));
        $this->builder->add('b',  autowire(TestNeedsDep::class));
        $this->builder->add('c',  autowire(TestMultiDepsWithCtor::class, arguments()->name('val1', new TypeReference(TestDependency::class))));
        $this->builder->add('d',  autowire(NoTypehintNoDefaultConstructorClass::class));

        $object = $this->whenGetType(TestMultiDepsWithCtor::class);

        self::assertInstanceOf(TestMultiDepsWithCtor::class, $object);

        $object = $this->whenGetType(
            NoTypehintNoDefaultConstructorClass::class,
        );

        self::assertInstanceOf(NoTypehintNoDefaultConstructorClass::class, $object);
        self::assertNull($object->testParam);
    }

    public function testMakeInstanceUsesReflectionForUnknownParamsInMultiBuildWithDepsAndVariadics(): void
    {
        require_once __DIR__ . "/fixtures_5_6.php";

        $this->builder->add('dep', autowire(TestDependency::class));
        $this->builder->add('test', autowire(NoTypehintNoDefaultConstructorVariadicClass::class));

        $object = $this->whenGetType(
            NoTypehintNoDefaultConstructorVariadicClass::class,
        );

        self::assertInstanceOf(NoTypehintNoDefaultConstructorVariadicClass::class, $object);
        self::assertEquals([], $object->testParam);
    }

    public function testMakeInstanceUsesReflectionForUnknownParamsWithDepsAndVariadicsWithTypeHint(): void
    {
        require_once __DIR__ . "/fixtures_5_6.php";

        $this->builder->add('dep', autowire(TestDependency::class));
        $this->builder->add('test', autowire(TypehintNoDefaultConstructorVariadicClass::class, arguments()->name('arg', new TypeReference(TestDependency::class))));

        $object = $this->whenGetType(TypehintNoDefaultConstructorVariadicClass::class);

        self::assertInstanceOf(TypehintNoDefaultConstructorVariadicClass::class, $object);
        self::assertIsArray($object->testParam);
        self::assertInstanceOf(TestDependency::class, $object->testParam[0]);
    }

    public function testMakeInstanceThrowsExceptionOnUntypehintedParameterWithoutDefinitionOrDefault(): void
    {
        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Failed to determine argument #0 ($val), because no definition matches');

        $this->builder->add('test', autowire(InjectorTestCtorParamWithNoTypehintOrDefault::class));
    }

    public function testMakeInstanceThrowsExceptionOnUninstantiableTypehintWithoutDefinition(): void
    {
        $this->builder->add('test', autowire(RequiresInterface::class));

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('No implementation found for type Amp\Injector\DepInterface');

        $this->whenGetType(RequiresInterface::class);
    }

    public function testTypelessDefineForDependency(): void
    {
        $this->builder->add('test', autowire(TypelessParameterDependency::class, arguments()->name('thumbnailSize', new ValueProvider(128))));
        $this->builder->add('main', autowire(RequiresDependencyWithTypelessParameters::class));

        $object = $this->whenGetType(RequiresDependencyWithTypelessParameters::class);

        self::assertEquals(128, $object->getThumbnailSize());
    }

    public function testMakeInstanceInjectsRawParametersDirectly(): void
    {
        $this->builder->add('test', autowire(InjectorTestRawCtorParams::class, arguments()
            ->name('string', new ValueProvider('string'))
            ->name('obj', new ValueProvider(new \StdClass))
            ->name('int', new ValueProvider(42))
            ->name('array', new ValueProvider([]))
            ->name('float', new ValueProvider(9.3))
            ->name('bool', new ValueProvider(true))
            ->name('null', new ValueProvider(null)))
        );

        $object = $this->whenGetType(InjectorTestRawCtorParams::class);

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
        $callable = $this->createMock(CallableMock::class);

        $this->builder->add('test', new DynamicProvider($callable));

        $callable->expects(self::once())
            ->method('__invoke')
            ->will(self::throwException(new \Exception('test exception')));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('test exception');

        $this->whenGet('test');
    }

    public function testMakeInstanceHandlesNamespacedClasses(): void
    {
        $this->builder->add('test', autowire(SomeClassName::class));

        $this->whenGetType(SomeClassName::class);

        $this->expectNotToPerformAssertions();
    }

    public function testMakeInstanceDelegate(): void
    {
        $callable = $this->createMock(CallableMock::class);

        $callable->expects(self::once())
            ->method('__invoke')
            ->willReturn(new TestDependency);

        $this->builder->add('test', new DynamicProvider($callable));

        $object = $this->whenGet('test');

        self::assertInstanceOf(TestDependency::class, $object);
    }

    public function testMakeInstanceWithStringDelegate(): void
    {
        self::markTestSkipped('Executable for delegation is not implemented, yet');

        $this->builder->add('test', new DynamicProvider(StringStdClassDelegateMock::class));

        $object = $this->whenGetType('StdClass');

        self::assertEquals(42, $object->test);
    }

    public function testMakeInstanceThrowsExceptionIfStringDelegateClassInstantiationFails(): void
    {
        self::markTestSkipped('Executable for delegation is not implemented, yet');

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Amp\Injector\Injector::delegate expects a valid callable or executable class::method string at Argument 2 but received \'SomeClassThatDefinitelyDoesNotExistForReal\'');

        $this->builder->add('test', new DynamicProvider('SomeClassThatDefinitelyDoesNotExistForReal'));
    }

    public function testMakeInstanceThrowsExceptionOnUntypehintedParameterWithNoDefinition(): void
    {
        $this->builder->add('test', autowire(RequiresInterface::class));

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('No implementation found for type Amp\Injector\DepInterface');

        $this->whenGetType(RequiresInterface::class);
    }

    public function testDefineAssignsPassedDefinition(): void
    {
        $this->builder->add('dep', autowire(DepImplementation::class));
        $this->builder->add('test', autowire(RequiresInterface::class, arguments()->name('dep', new TypeReference(DepImplementation::class))));

        self::assertInstanceOf(RequiresInterface::class, $this->whenGetType(RequiresInterface::class));
    }

    public function testShareStoresSharedInstanceAndReturnsCurrentInstance(): void
    {
        $testShare = new \StdClass;
        $testShare->test = 42;

        $this->builder->add('std', new ValueProvider($testShare));

        $testShare->test = 'test';

        self::assertEquals('test', $this->whenGetType('stdclass')->test);
        self::assertEquals('test', $this->whenGet('std')->test);
    }

    private function whenGet(string $id): mixed
    {
        $context = $this->builder->build();

        return $context->get($id);
    }

    // /**
    //  * @dataProvider provideInvalidDelegates
    //  */
    // public function testDelegateThrowsExceptionIfDelegateIsNotCallableOrString($badDelegate): void
    // {
    //     $this->expectException(InjectionException::class);
    //     $this->expectExceptionMessage('Amp\Injector\Injector::delegate expects a valid callable or executable class::method string at Argument 2');
//
    //     $injector->delegate(TestDependency::class, $badDelegate);
    // }

    public function provideInvalidDelegates(): array
    {
        return [
            [new \StdClass],
            [42],
            [true],
        ];
    }

    public function testDelegateInstantiatesCallableClassString(): void
    {
        self::markTestSkipped('No executable factory, yet');

        $this->builder->add('test', new DynamicProvider(CallableDelegateClassTest::class));

        self::assertInstanceof(MadeByDelegate::class, $this->whenGetType(MadeByDelegate::class));
    }

    public function testDelegateInstantiatesCallableClassArray(): void
    {
        self::markTestSkipped('No executable delegate, yet');

        $this->builder->add(__METHOD__, new DynamicProvider([CallableDelegateClassTest::class, '__invoke']));

        self::assertInstanceof(MadeByDelegate::class, $this->whenGetType(MadeByDelegate::class));
    }

    public function testUnknownDelegationFunction(): void
    {
        self::markTestSkipped('No executable delegate, yet');

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('FunctionWhichDoesNotExist');

        $this->builder->add(__METHOD__, new DynamicProvider('FunctionWhichDoesNotExist'));
    }

    public function testUnknownDelegationMethod(): void
    {
        self::markTestSkipped('No executable delegate, yet');

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('stdClass');

        $this->builder->add(__METHOD__, new DynamicProvider([\stdClass::class, 'methodWhichDoesNotExist']));
    }

//    /**
//     * @dataProvider provideExecutionExpectations
//     */
//    public function testProvisionedInvokables($toInvoke, $definition, $expectedResult): void
//    {
//        self::assertEquals($expectedResult, $injector->execute($toInvoke, $definition));
//    }

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

    // public function testStaticStringInvokableWithArgument(): void
    // {
    //     $invokable = $injector->buildExecutable('Amp\Injector\ClassWithStaticMethodThatTakesArg::doSomething');
    //     self::assertEquals(42, $invokable(41));
    // }

    public function testMissingDependency(): void
    {
        self::markTestSkipped('No autowiring of unknown dependencies, yet');

        $this->builder->add('test', autowire(TestMissingDependency::class));

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Could not make Amp\Injector\TypoInTypehint: Class "Amp\Injector\TypoInTypehint" does not exist');

        $this->whenGetType(TestMissingDependency::class);
    }

    public function testAliasingConcreteClasses(): void
    {
        $this->builder->add('1',  autowire(ConcreteClass1::class));
        $this->builder->add('2',  autowire(ConcreteClass2::class));
        $this->builder->primary(ConcreteClass1::class, '2');

        $object = $this->whenGetType(ConcreteClass1::class);

        self::assertInstanceOf(ConcreteClass2::class, $object);
    }

    public function testSharedByAliasedInterfaceName(): void
    {
        $this->builder->add('test', singleton(autowire(SharedClass::class)));

        $object1 = $this->whenGetType(SharedAliasedInterface::class);
        $object2 = $this->whenGetType(SharedAliasedInterface::class);

        self::assertSame($object1, $object2);
    }

    public function testNotSharedByAliasedInterfaceName(): void
    {
        $this->builder->add('test', autowire(NotSharedClass::class));

        $object1 = $this->whenGetType(SharedAliasedInterface::class);
        $object2 = $this->whenGetType(SharedAliasedInterface::class);

        self::assertNotSame($object1, $object2);
    }

    public function testSharedByAliasedInterfaceNameWithParameter(): void
    {
        $this->builder->add('shared', singleton(autowire(SharedClass::class)));
        $this->builder->add('alias', autowire(ClassWithAliasAsParameter::class));

        $sharedObject = $this->whenGetType(SharedAliasedInterface::class);
        $childClass = $this->whenGetType(ClassWithAliasAsParameter::class);

        self::assertSame($sharedObject, $childClass->sharedClass);
    }

    public function testDependencyWhereSharedWithProtectedConstructor(): void
    {
        $this->builder->add('test', autowire(TestNeedsDepWithProtCons::class));

        $inner = TestDependencyWithProtectedConstructor::create();
        $this->builder->add('inner', new ValueProvider($inner));

        $outer = $this->whenGetType(TestNeedsDepWithProtCons::class);

        self::assertSame($inner, $outer->dep);
    }

    public function testBugWithReflectionPoolIncorrectlyReturningBadInfo(): void
    {
        $this->builder->add('a',  autowire(ClassOuter::class));
        $this->builder->add('b',  autowire(ClassInnerA::class));
        $this->builder->add('c',  autowire(ClassInnerB::class));

        $object = $this->whenGetType(ClassOuter::class);

        self::assertInstanceOf(ClassOuter::class, $object);
        self::assertInstanceOf(ClassInnerA::class, $object->dep);
        self::assertInstanceOf(ClassInnerB::class, $object->dep->dep);
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

        $this->whenGetType($class);
    }

    public function testNonConcreteDependencyWithDefault(): void
    {
        $this->builder->add('test', autowire(NonConcreteDependencyWithDefaultValue::class));

        $object = $this->whenGetType(NonConcreteDependencyWithDefaultValue::class);

        self::assertInstanceOf(NonConcreteDependencyWithDefaultValue::class, $object);
        self::assertNull($object->interface);
    }

    public function testNonConcreteDependencyWithDefaultValueThroughAlias(): void
    {
        self::markTestSkipped('FIXME');

        $this->builder->add('a', singleton(autowire( ImplementsInterface::class)));
        $this->builder->add('b', singleton(autowire( NonConcreteDependencyWithDefaultValue::class)));

        $class = $this->whenGetType(NonConcreteDependencyWithDefaultValue::class);

        self::assertInstanceOf(NonConcreteDependencyWithDefaultValue::class, $class);
        // FIXME: Broken, because param is optional
        self::assertInstanceOf(ImplementsInterface::class, $class->interface);
    }

    public function testNonConcreteDependencyWithDefaultValueThroughDelegation(): void
    {
        self::markTestSkipped('No executable delegate, yet');

        $this->builder->add('foo', new DynamicProvider(ImplementsInterfaceFactory::class));

        $class = $this->whenGetType(NonConcreteDependencyWithDefaultValue::class);

        self::assertInstanceOf(NonConcreteDependencyWithDefaultValue::class, $class);
        self::assertInstanceOf(ImplementsInterface::class, $class->interface);
    }

    public function testDependencyWithDefaultValueThroughShare(): void
    {
        self::markTestSkipped('FIXME');

        $this->builder->add('test', autowire(ConcreteDependencyWithDefaultValue::class));

        // Instance is not shared, null default is used for dependency
        $instance = $this->whenGetType(ConcreteDependencyWithDefaultValue::class);
        self::assertNull($instance->dependency);

        // Instance is explicitly shared, $instance is used for dependency
        $this->builder->add('std', new ValueProvider(new \stdClass));

        $instance = $this->whenGetType(ConcreteDependencyWithDefaultValue::class);
        self::assertInstanceOf(\stdClass::class, $instance->dependency);
    }

    public function testAppropriateExceptionThrownOnNonPublicConstructor(): void
    {
        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Cannot instantiate protected/private constructor in class Amp\Injector\HasNonPublicConstructor');

        $this->builder->add('test', autowire(HasNonPublicConstructor::class));
    }

    public function testAppropriateExceptionThrownOnNonPublicConstructorWithArgs(): void
    {
        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Cannot instantiate protected/private constructor in class Amp\Injector\HasNonPublicConstructorWithArgs');

        $this->builder->add('test', autowire(HasNonPublicConstructorWithArgs::class));
    }

    // public function testMakeExecutableFailsOnNonExistentFunction(): void
    // {
    //     $this->expectException(InjectionException::class);
    //     $this->expectExceptionMessage('nonExistentFunction');
//
    //     $injector->buildExecutable('nonExistentFunction');
    // }

    // public function testMakeExecutableFailsOnNonExistentInstanceMethod(): void
    // {
    //     $object = new \StdClass;
//
    //     $this->expectException(InjectionException::class);
    //     $this->expectExceptionMessage("[object(stdClass), 'nonExistentMethod']");
    //     $this->expectExceptionCode(Injector::E_INVOKABLE);
//
    //     $injector->buildExecutable([$object, 'nonExistentMethod']);
    // }

    // public function testMakeExecutableFailsOnNonExistentStaticMethod(): void
    // {
    //     $this->expectException(InjectionException::class);
    //     $this->expectExceptionMessage("StdClass::nonExistentMethod");
//
    //     $injector->buildExecutable(['StdClass', 'nonExistentMethod']);
    // }

    // public function testMakeExecutableFailsOnClassWithoutInvoke(): void
    // {
    //     $injector = new Injector();
    //     $object = new \StdClass();
//
    //     $this->expectException(InjectionException::class);
    //     $this->expectExceptionMessage('Invalid invokable: callable or provisional string required');
    //     $this->expectExceptionCode(Injector::E_INVOKABLE);
//
    //     $injector->buildExecutable($object);
    // }

    public function testDefineWithBackslashAndMakeWithoutBackslash(): void
    {
        $this->builder->add('test', autowire('\\' . SimpleNoTypehintClass::class, arguments()->name('arg', new ValueProvider('tested'))));

        $object = $this->whenGetType(SimpleNoTypehintClass::class);

        self::assertEquals('tested', $object->testParam);
    }

    public function testDefineWithoutBackslashAndMakeWithBackslash(): void
    {
        $this->builder->add('test', autowire(SimpleNoTypehintClass::class, arguments()->name('arg', new ValueProvider('tested'))));

        $object = $this->whenGetType('\\' . SimpleNoTypehintClass::class);

        self::assertEquals('tested', $object->testParam);
    }

    // public function testInstanceMutate(): void
    // {
    //     $injector = new Injector();
    //     $injector->prepare('\StdClass', function ($obj) {
    //         $obj->testval = 42;
    //     });
    //     $obj = $this->whenGetType('StdClass');
//
    //     self::assertSame(42, $obj->testval);
    // }

    // public function testInterfaceMutate(): void
    // {
    //     $injector = new Injector();
    //     $injector->prepare(SomeInterface::class, function ($obj) {
    //         $obj->testProp = 42;
    //     });
    //     $obj = $this->whenGetType(PreparesImplementationTest::class);
//
    //     self::assertSame(42, $obj->testProp);
    // }

    public function testDelegationFunction(): void
    {
        $this->builder->add('test', new DynamicProvider('Amp\Injector\createTestDelegationSimple'));
        $this->builder->primary(TestDelegationSimple::class, 'test');

        $object = $this->whenGetType(TestDelegationSimple::class);

        self::assertInstanceOf(TestDelegationSimple::class, $object);
        self::assertTrue($object->delegateCalled);
    }

    public function testDelegationDependency(): void
    {
        self::markTestSkipped('Not implemented');

        $this->builder->add('test', new DynamicProvider('Amp\Injector\createTestDelegationDependency'));

        $object = $this->whenGetType(TestDelegationDependency::class);

        self::assertInstanceOf(TestDelegationDependency::class, $object);
        self::assertTrue($object->delegateCalled);
    }

//     public function testExecutableAliasing(): void
//     {
//         $injector->alias(BaseExecutableClass::class, ExtendsExecutableClass::class);
//         $result = $injector->execute([BaseExecutableClass::class, 'foo']);
//         self::assertEquals('This is the ExtendsExecutableClass', $result);
//     }

//     public function testExecutableAliasingStatic(): void
//     {
//         $injector = new Injector();
//         $injector->alias(BaseExecutableClass::class, ExtendsExecutableClass::class);
//         $result = $injector->execute([BaseExecutableClass::class, 'bar']);
//         self::assertEquals('This is the ExtendsExecutableClass', $result);
//     }

    /**
     * Test coverage for delegate closures that are defined outside of a class.
     */
    public function testDelegateClosure(): void
    {
        $delegateClosure = getDelegateClosureInGlobalScope();
        $this->builder->add('test', new DynamicProvider($delegateClosure));
        $this->builder->primary(DelegateClosureInGlobalScope::class, 'test');

        $this->whenGetType(DelegateClosureInGlobalScope::class);

        $this->expectNotToPerformAssertions();
    }

    public function testDelegationDoesntMakeObject(): void
    {
        self::markTestSkipped('FIXME');

        $delegate = function () {
            return null;
        };

        $this->builder->add('test', new DynamicProvider($delegate));

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Making amp\injector\someclassname did not result in an object, instead result is of type \'NULL\'');

        $this->whenGetType(SomeClassName::class);
    }

    public function testDelegationDoesntMakeObjectMakesString(): void
    {
        self::markTestSkipped('FIXME');

        $this->builder->add('test', new DynamicProvider(fn() => 'ThisIsNotAClass'));

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Making amp\injector\someclassname did not result in an object, instead result is of type \'string\'');

        $this->whenGetType(SomeClassName::class);
    }

    public function testChildWithoutConstructorWorks(): void
    {
        $this->builder->add('parent', singleton(autowire(ParentWithConstructor::class, arguments()->name('foo', new ValueProvider('parent')))));
        $this->builder->add('child', singleton(autowire(ChildWithoutConstructor::class, arguments()->name('foo', new ValueProvider('child')))));

        $child = $this->whenGetType(ChildWithoutConstructor::class);
        self::assertEquals('child', $child->foo);

        // TODO: Should this be that way?
        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Conflict: Multiple implementations found for amp\injector\parentwithconstructor');

        $parent = $this->whenGetType(ParentWithConstructor::class);
        self::assertEquals('parent', $parent->foo);
    }

    public function testChildWithoutConstructorMissingParam(): void
    {
        $this->builder->add('parent', singleton(autowire(ParentWithConstructor::class, arguments()->name('foo', new ValueProvider('parent')))));

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Failed to determine argument #0 ($foo), because no definition matches');

        $this->builder->add('child', singleton(autowire(ChildWithoutConstructor::class, arguments())));
    }

    public function testThatExceptionInConstructorDoesntCauseCyclicDependencyException(): void
    {
        $this->builder->add('test', autowire(ThrowsExceptionInConstructor::class));

        try {
            $this->whenGetType(ThrowsExceptionInConstructor::class);
        } catch (\Exception) {
            // ignore
        }

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Exception in constructor');

        $this->whenGetType(ThrowsExceptionInConstructor::class);
    }

    protected function setUp(): void
    {
        $this->builder = new ContextBuilder;
    }
}
