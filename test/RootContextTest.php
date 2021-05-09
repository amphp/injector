<?php

namespace Amp\Injector;

use Amp\Injector\Argument\TypeReference;
use Amp\Injector\Argument\Value;
use Amp\Injector\Provider\CallableProvider;
use PHPUnit\Framework\TestCase;

class RootContextTest extends TestCase
{
    private ContextFactory $contextFactory;

    public function testMakeInstanceInjectsSimpleConcreteDependency(): void
    {
        $this->contextFactory->prototype('test', TestNeedsDep::class);
        $this->contextFactory->prototype('dependency', TestDependency::class);

        $instance = $this->whenGetType(TestNeedsDep::class);

        self::assertEquals(
            new TestNeedsDep(new TestDependency),
            $instance
        );
    }

    private function whenGetType(string $class): object
    {
        $context = $this->contextFactory->build();

        return $context->getType($class);
    }

    public function testMakeInstanceReturnsNewInstanceIfClassHasNoConstructor(): void
    {
        $this->contextFactory->prototype('test', TestNoConstructor::class);

        self::assertEquals(new TestNoConstructor, $this->whenGetType(TestNoConstructor::class));
    }

    public function testMakeInstanceReturnsAliasInstanceOnNonConcreteTypehint(): void
    {
        $this->contextFactory->prototype(__METHOD__, DepImplementation::class);

        self::assertEquals(new DepImplementation, $this->whenGetType(DepInterface::class));
    }

    public function testMakeInstanceThrowsExceptionOnInterfaceWithoutAlias(): void
    {
        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('No definition found for Amp\Injector\DepInterface');

        $this->whenGetType(DepInterface::class);
    }

    public function testMakeInstanceThrowsExceptionOnNonConcreteCtorParamWithoutImplementation(): void
    {
        $this->contextFactory->prototype('test', RequiresInterface::class);

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('No definition found for Amp\Injector\DepInterface');

        $this->whenGetType(RequiresInterface::class);
    }

    public function testMakeInstanceBuildsNonConcreteCtorParamWithAlias(): void
    {
        $this->contextFactory->prototype('impl', DepImplementation::class);
        $this->contextFactory->prototype('reqInterface', RequiresInterface::class);

        $object = $this->whenGetType(RequiresInterface::class);

        self::assertInstanceOf(RequiresInterface::class, $object);
    }

    public function testMakeInstancePassesNullCtorParameterIfNoTypehintOrDefaultCanBeDetermined(): void
    {
        $this->contextFactory->prototype('test', ProvTestNoDefinitionNullDefaultClass::class);

        $object = $this->whenGetType(ProvTestNoDefinitionNullDefaultClass::class);

        self::assertEquals(new ProvTestNoDefinitionNullDefaultClass, $object);
        self::assertNull($object->arg);
    }

    public function testMakeInstanceReturnsSharedInstanceIfAvailable(): void
    {
        $this->contextFactory->prototype('depImpl', DepImplementation::class);
        $this->contextFactory->singleton('reqInterface', RequiresInterface::class, arguments()->name('dep', new TypeReference(DepImplementation::class)));

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
        $this->contextFactory->prototype('a', TestDependency::class);
        $this->contextFactory->prototype('b', TestNeedsDep::class);
        $this->contextFactory->prototype('c', TestMultiDepsWithCtor::class, arguments()->name('val1', new TypeReference(TestDependency::class)));
        $this->contextFactory->prototype('d', NoTypehintNoDefaultConstructorClass::class);

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

        $this->contextFactory->prototype('dep', TestDependency::class);
        $this->contextFactory->prototype('test', NoTypehintNoDefaultConstructorVariadicClass::class);

        $object = $this->whenGetType(
            NoTypehintNoDefaultConstructorVariadicClass::class,
        );

        self::assertInstanceOf(NoTypehintNoDefaultConstructorVariadicClass::class, $object);
        self::assertEquals([], $object->testParam);
    }

    public function testMakeInstanceUsesReflectionForUnknownParamsWithDepsAndVariadicsWithTypeHint(): void
    {
        require_once __DIR__ . "/fixtures_5_6.php";

        $this->contextFactory->prototype('dep', TestDependency::class);
        $this->contextFactory->prototype('test', TypehintNoDefaultConstructorVariadicClass::class, arguments()->name('arg', new TypeReference(TestDependency::class)));

        $object = $this->whenGetType(TypehintNoDefaultConstructorVariadicClass::class);

        self::assertInstanceOf(TypehintNoDefaultConstructorVariadicClass::class, $object);
        self::assertIsArray($object->testParam);
        self::assertInstanceOf(TestDependency::class, $object->testParam[0]);
    }

    public function testMakeInstanceThrowsExceptionOnUntypehintedParameterWithoutDefinitionOrDefault(): void
    {
        $this->contextFactory->prototype('test', InjectorTestCtorParamWithNoTypehintOrDefault::class);

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Failed to provide argument #0 ($val), because no definition exists to provide it');

        $this->whenGetType(InjectorTestCtorParamWithNoTypehintOrDefault::class);
    }

    public function testMakeInstanceThrowsExceptionOnUninstantiableTypehintWithoutDefinition(): void
    {
        $this->contextFactory->prototype('test', RequiresInterface::class);

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('No definition found for Amp\Injector\DepInterface');

        $this->whenGetType(RequiresInterface::class);
    }

    public function testTypelessDefineForDependency(): void
    {
        $this->contextFactory->prototype('test', TypelessParameterDependency::class, arguments()->name('thumbnailSize', new Value(128)));
        $this->contextFactory->prototype('main', RequiresDependencyWithTypelessParameters::class);

        $object = $this->whenGetType(RequiresDependencyWithTypelessParameters::class);

        self::assertEquals(128, $object->getThumbnailSize());
    }

    public function testMakeInstanceInjectsRawParametersDirectly(): void
    {
        $this->contextFactory->prototype(__METHOD__, InjectorTestRawCtorParams::class, arguments()
            ->name('string', new Value('string'))
            ->name('obj', new Value(new \StdClass))
            ->name('int', new Value(42))
            ->name('array', new Value([]))
            ->name('float', new Value(9.3))
            ->name('bool', new Value(true))
            ->name('null', new Value(null))
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

        $this->contextFactory->add(__METHOD__, TestDependency::class, new CallableProvider($callable));

        $callable->expects(self::once())
            ->method('__invoke')
            ->will(self::throwException(new \Exception('test exception')));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('test exception');

        $this->whenGetType(TestDependency::class);
    }

    public function testMakeInstanceHandlesNamespacedClasses(): void
    {
        $this->contextFactory->prototype('test', SomeClassName::class);

        $this->whenGetType(SomeClassName::class);

        $this->expectNotToPerformAssertions();
    }

    public function testMakeInstanceDelegate(): void
    {
        $callable = $this->createMock(CallableMock::class);

        $callable->expects(self::once())
            ->method('__invoke')
            ->willReturn(new TestDependency);

        $this->contextFactory->add(__METHOD__, TestDependency::class, new CallableProvider($callable));

        $object = $this->whenGetType(TestDependency::class);

        self::assertInstanceOf(TestDependency::class, $object);
    }

    public function testMakeInstanceWithStringDelegate(): void
    {
        self::markTestSkipped('Executable for delegation is not implemented, yet');

        $this->contextFactory->add('test', 'StdClass', new CallableProvider(StringStdClassDelegateMock::class));

        $object = $this->whenGetType('StdClass');

        self::assertEquals(42, $object->test);
    }

    public function testMakeInstanceThrowsExceptionIfStringDelegateClassInstantiationFails(): void
    {
        self::markTestSkipped('Executable for delegation is not implemented, yet');

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Amp\Injector\Injector::delegate expects a valid callable or executable class::method string at Argument 2 but received \'SomeClassThatDefinitelyDoesNotExistForReal\'');

        $this->contextFactory->add('test', 'StdClass', new CallableProvider('SomeClassThatDefinitelyDoesNotExistForReal'));
    }

    public function testMakeInstanceThrowsExceptionOnUntypehintedParameterWithNoDefinition(): void
    {
        $this->contextFactory->prototype('test', RequiresInterface::class);

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('No definition found for Amp\Injector\DepInterface');

        $this->whenGetType(RequiresInterface::class);
    }

    public function testDefineAssignsPassedDefinition(): void
    {
        $this->contextFactory->prototype('dep', DepImplementation::class);
        $this->contextFactory->prototype('test', RequiresInterface::class, arguments()->name('dep', new TypeReference(DepImplementation::class)));

        self::assertInstanceOf(RequiresInterface::class, $this->whenGetType(RequiresInterface::class));
    }

    public function testShareStoresSharedInstanceAndReturnsCurrentInstance(): void
    {
        $testShare = new \StdClass;
        $testShare->test = 42;

        $this->contextFactory->value('std', $testShare);

        $testShare->test = 'test';

        self::assertEquals('test', $this->whenGetType('stdclass')->test);
        self::assertEquals('test', $this->whenGet('std')->test);
    }

    private function whenGet(string $id): mixed
    {
        $context = $this->contextFactory->build();

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

        $this->contextFactory->add('test', MadeByDelegate::class, new CallableProvider(CallableDelegateClassTest::class));

        self::assertInstanceof(MadeByDelegate::class, $this->whenGetType(MadeByDelegate::class));
    }

    public function testDelegateInstantiatesCallableClassArray(): void
    {
        self::markTestSkipped('No executable delegate, yet');

        $this->contextFactory->add(__METHOD__, MadeByDelegate::class, new CallableProvider([CallableDelegateClassTest::class, '__invoke']));

        self::assertInstanceof(MadeByDelegate::class, $this->whenGetType(MadeByDelegate::class));
    }

    public function testUnknownDelegationFunction(): void
    {
        self::markTestSkipped('No executable delegate, yet');

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('FunctionWhichDoesNotExist');

        $this->contextFactory->add(__METHOD__, DelegatableInterface::class, new CallableProvider('FunctionWhichDoesNotExist'));
    }

    public function testUnknownDelegationMethod(): void
    {
        self::markTestSkipped('No executable delegate, yet');

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('stdClass');

        $this->contextFactory->add(__METHOD__, DelegatableInterface::class, new CallableProvider([\stdClass::class, 'methodWhichDoesNotExist']));
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

        $this->contextFactory->prototype('test', TestMissingDependency::class);

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Could not make Amp\Injector\TypoInTypehint: Class "Amp\Injector\TypoInTypehint" does not exist');

        $this->whenGetType(TestMissingDependency::class);
    }

    public function testAliasingConcreteClasses(): void
    {
        $this->contextFactory->prototype('1', ConcreteClass1::class);
        $this->contextFactory->prototype('2', ConcreteClass2::class);
        $this->contextFactory->primary(ConcreteClass1::class, '2');

        $object = $this->whenGetType(ConcreteClass1::class);

        self::assertInstanceOf(ConcreteClass2::class, $object);
    }

    public function testSharedByAliasedInterfaceName(): void
    {
        $this->contextFactory->singleton(__METHOD__, SharedClass::class);

        $object1 = $this->whenGetType(SharedAliasedInterface::class);
        $object2 = $this->whenGetType(SharedAliasedInterface::class);

        self::assertSame($object1, $object2);
    }

    public function testNotSharedByAliasedInterfaceName(): void
    {
        $this->contextFactory->prototype(__METHOD__, NotSharedClass::class);

        $object1 = $this->whenGetType(SharedAliasedInterface::class);
        $object2 = $this->whenGetType(SharedAliasedInterface::class);

        self::assertNotSame($object1, $object2);
    }

    public function testSharedByAliasedInterfaceNameWithParameter(): void
    {
        $this->contextFactory->singleton('shared', SharedClass::class);
        $this->contextFactory->prototype('alias', ClassWithAliasAsParameter::class);

        $sharedObject = $this->whenGetType(SharedAliasedInterface::class);
        $childClass = $this->whenGetType(ClassWithAliasAsParameter::class);

        self::assertSame($sharedObject, $childClass->sharedClass);
    }

    public function testDependencyWhereSharedWithProtectedConstructor(): void
    {
        $this->contextFactory->prototype('test', TestNeedsDepWithProtCons::class);

        $inner = TestDependencyWithProtectedConstructor::create();
        $this->contextFactory->value('inner', $inner);

        $outer = $this->whenGetType(TestNeedsDepWithProtCons::class);

        self::assertSame($inner, $outer->dep);
    }

    public function testBugWithReflectionPoolIncorrectlyReturningBadInfo(): void
    {
        $this->contextFactory->prototype('a', ClassOuter::class);
        $this->contextFactory->prototype('b', ClassInnerA::class);
        $this->contextFactory->prototype('c', ClassInnerB::class);

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
        $this->contextFactory->prototype('test', NonConcreteDependencyWithDefaultValue::class);

        $object = $this->whenGetType(NonConcreteDependencyWithDefaultValue::class);

        self::assertInstanceOf(NonConcreteDependencyWithDefaultValue::class, $object);
        self::assertNull($object->interface);
    }

    public function testNonConcreteDependencyWithDefaultValueThroughAlias(): void
    {
        self::markTestSkipped('FIXME');

        $this->contextFactory->singleton('a', ImplementsInterface::class);
        $this->contextFactory->singleton('b', NonConcreteDependencyWithDefaultValue::class);

        $class = $this->whenGetType(NonConcreteDependencyWithDefaultValue::class);

        self::assertInstanceOf(NonConcreteDependencyWithDefaultValue::class, $class);
        // FIXME: Broken, because param is optional
        self::assertInstanceOf(ImplementsInterface::class, $class->interface);
    }

    public function testNonConcreteDependencyWithDefaultValueThroughDelegation(): void
    {
        self::markTestSkipped('No executable delegate, yet');

        $this->contextFactory->add('foo', DelegatableInterface::class, new CallableProvider(ImplementsInterfaceFactory::class));

        $class = $this->whenGetType(NonConcreteDependencyWithDefaultValue::class);

        self::assertInstanceOf(NonConcreteDependencyWithDefaultValue::class, $class);
        self::assertInstanceOf(ImplementsInterface::class, $class->interface);
    }

    public function testDependencyWithDefaultValueThroughShare(): void
    {
        self::markTestSkipped('FIXME');

        $this->contextFactory->prototype('test', ConcreteDependencyWithDefaultValue::class);

        // Instance is not shared, null default is used for dependency
        $instance = $this->whenGetType(ConcreteDependencyWithDefaultValue::class);
        self::assertNull($instance->dependency);

        // Instance is explicitly shared, $instance is used for dependency
        $this->contextFactory->value('std', new \stdClass);

        $instance = $this->whenGetType(ConcreteDependencyWithDefaultValue::class);
        self::assertInstanceOf(\stdClass::class, $instance->dependency);
    }

    public function testAppropriateExceptionThrownOnNonPublicConstructor(): void
    {
        $this->contextFactory->prototype('test', HasNonPublicConstructor::class);

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Cannot instantiate protected/private constructor in class Amp\Injector\HasNonPublicConstructor');

        $this->whenGetType(HasNonPublicConstructor::class);
    }

    public function testAppropriateExceptionThrownOnNonPublicConstructorWithArgs(): void
    {
        $this->contextFactory->prototype('test', HasNonPublicConstructorWithArgs::class);

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Cannot instantiate protected/private constructor in class Amp\Injector\HasNonPublicConstructorWithArgs');

        $this->whenGetType(HasNonPublicConstructorWithArgs::class);
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
        $this->contextFactory->prototype('test', '\\' . SimpleNoTypehintClass::class, arguments()->name('arg', new Value('tested')));

        $object = $this->whenGetType(SimpleNoTypehintClass::class);

        self::assertEquals('tested', $object->testParam);
    }

    public function testDefineWithoutBackslashAndMakeWithBackslash(): void
    {
        $this->contextFactory->prototype('test', SimpleNoTypehintClass::class, arguments()->name('arg', new Value('tested')));

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
        $this->contextFactory->add('test', TestDelegationSimple::class, new CallableProvider('Amp\Injector\createTestDelegationSimple'));

        $object = $this->whenGetType(TestDelegationSimple::class);

        self::assertInstanceOf(TestDelegationSimple::class, $object);
        self::assertTrue($object->delegateCalled);
    }

    public function testDelegationDependency(): void
    {
        self::markTestSkipped('Not implemented');

        $this->contextFactory->add('test', TestDelegationDependency::class, new CallableProvider('Amp\Injector\createTestDelegationDependency'));

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
        $this->contextFactory->add('test', DelegateClosureInGlobalScope::class, new CallableProvider($delegateClosure));

        $this->whenGetType(DelegateClosureInGlobalScope::class);

        $this->expectNotToPerformAssertions();
    }

    public function testDelegationDoesntMakeObject(): void
    {
        self::markTestSkipped('FIXME');

        $delegate = function () {
            return null;
        };

        $this->contextFactory->add('test', SomeClassName::class, new CallableProvider($delegate));

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Making amp\injector\someclassname did not result in an object, instead result is of type \'NULL\'');

        $this->whenGetType(SomeClassName::class);
    }

    public function testDelegationDoesntMakeObjectMakesString(): void
    {
        self::markTestSkipped('FIXME');

        $this->contextFactory->add('test', SomeClassName::class, new CallableProvider(fn() => 'ThisIsNotAClass'));

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Making amp\injector\someclassname did not result in an object, instead result is of type \'string\'');

        $this->whenGetType(SomeClassName::class);
    }

    public function testChildWithoutConstructorWorks(): void
    {
        $this->contextFactory->singleton('parent', ParentWithConstructor::class, arguments()->name('foo', new Value('parent')));
        $this->contextFactory->singleton('child', ChildWithoutConstructor::class, arguments()->name('foo', new Value('child')));

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
        $this->contextFactory->singleton('parent', ParentWithConstructor::class, arguments()->name('foo', new Value('parent')));
        $this->contextFactory->singleton('child', ChildWithoutConstructor::class, arguments());

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Failed to provide argument #0 ($foo), because no definition exists');

        $this->whenGetType(ChildWithoutConstructor::class);
    }

    public function testThatExceptionInConstructorDoesntCauseCyclicDependencyException(): void
    {
        $this->contextFactory->prototype('test', ThrowsExceptionInConstructor::class);

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
        $this->contextFactory = new ContextFactory;
    }
}
