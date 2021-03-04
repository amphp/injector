<?php

namespace Amp\Injector;

use PHPUnit\Framework\TestCase;

class InjectorTest extends TestCase
{
    public function testArrayTypehintDoesNotEvaluatesAsClass(): void
    {
        $injector = new Injector;
        $injector->defineParam('parameter', []);
        $injector->execute('Amp\Injector\hasArrayDependency');
    }

    public function testMakeInstanceInjectsSimpleConcreteDependency(): void
    {
        $injector = new Injector;
        self::assertEquals(
            new TestNeedsDep(new TestDependency),
            $injector->make(TestNeedsDep::class)
        );
    }

    public function testMakeInstanceReturnsNewInstanceIfClassHasNoConstructor(): void
    {
        $injector = new Injector;
        self::assertEquals(new TestNoConstructor, $injector->make(TestNoConstructor::class));
    }

    public function testMakeInstanceReturnsAliasInstanceOnNonConcreteTypehint(): void
    {
        $injector = new Injector;
        $injector->alias(DepInterface::class, DepImplementation::class);
        self::assertEquals(new DepImplementation, $injector->make(DepInterface::class));
    }

    public function testMakeInstanceThrowsExceptionOnInterfaceWithoutAlias(): void
    {
        $injector = new Injector;

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Injection definition required for interface Amp\Injector\DepInterface');
        $this->expectExceptionCode(Injector::E_NEEDS_DEFINITION);

        $injector->make(DepInterface::class);
    }

    public function testMakeInstanceThrowsExceptionOnNonConcreteCtorParamWithoutImplementation(): void
    {
        $injector = new Injector;

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Injection definition required for interface Amp\Injector\DepInterface');
        $this->expectExceptionCode(Injector::E_NEEDS_DEFINITION);

        $injector->make(RequiresInterface::class);
    }

    public function testMakeInstanceBuildsNonConcreteCtorParamWithAlias(): void
    {
        $injector = new Injector;
        $injector->alias(DepInterface::class, DepImplementation::class);
        $object = $injector->make(RequiresInterface::class);
        self::assertInstanceOf(RequiresInterface::class, $object);
    }

    public function testMakeInstancePassesNullCtorParameterIfNoTypehintOrDefaultCanBeDetermined(): void
    {
        $injector = new Injector;
        $nullCtorParamObj = $injector->make(ProvTestNoDefinitionNullDefaultClass::class);
        self::assertEquals(new ProvTestNoDefinitionNullDefaultClass, $nullCtorParamObj);
        self::assertNull($nullCtorParamObj->arg);
    }

    public function testMakeInstanceReturnsSharedInstanceIfAvailable(): void
    {
        $injector = new Injector;
        $injector->define(RequiresInterface::class, ['dep' => DepImplementation::class]);
        $injector->share(RequiresInterface::class);
        $injected = $injector->make(RequiresInterface::class);

        self::assertEquals('something', $injected->testDep->testProp);
        $injected->testDep->testProp = 'something else';

        $injected2 = $injector->make(RequiresInterface::class);
        self::assertEquals('something else', $injected2->testDep->testProp);
    }

    public function testMakeInstanceThrowsExceptionOnClassLoadFailure(): void
    {
        $injector = new Injector;

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Could not make ClassThatDoesntExist: Class "ClassThatDoesntExist" does not exist');

        $injector->make('ClassThatDoesntExist');
    }

    public function testMakeInstanceUsesCustomDefinitionIfSpecified(): void
    {
        $injector = new Injector;
        $injector->define(TestNeedsDep::class, ['testDep' => TestDependency::class]);
        $injected = $injector->make(TestNeedsDep::class, ['testDep' => TestDependency2::class]);
        self::assertEquals('testVal2', $injected->testDep->testProp);
    }

    public function testMakeInstanceCustomDefinitionOverridesExistingDefinitions(): void
    {
        $injector = new Injector;
        $injector->define(InjectorTestChildClass::class,
            [':arg1' => 'First argument', ':arg2' => 'Second argument']);
        $injected = $injector->make(InjectorTestChildClass::class, [':arg1' => 'Override']);
        self::assertEquals('Override', $injected->arg1);
        self::assertEquals('Second argument', $injected->arg2);
    }

    public function testMakeInstanceStoresShareIfMarkedWithNullInstance(): void
    {
        $injector = new Injector;
        $injector->share(TestDependency::class);
        $injector->make(TestDependency::class);

        // TODO Assertions
    }

    public function testMakeInstanceUsesReflectionForUnknownParamsInMultiBuildWithDeps(): void
    {
        $injector = new Injector;
        $object = $injector->make(TestMultiDepsWithCtor::class, ['val1' => TestDependency::class]);
        self::assertInstanceOf(TestMultiDepsWithCtor::class, $object);

        $object = $injector->make(
            NoTypehintNoDefaultConstructorClass::class,
            ['val1' => TestDependency::class]
        );
        self::assertInstanceOf(NoTypehintNoDefaultConstructorClass::class, $object);
        self::assertNull($object->testParam);
    }

    /**
     * @requires PHP 5.6
     */
    public function testMakeInstanceUsesReflectionForUnknownParamsInMultiBuildWithDepsAndVariadics(): void
    {
        if (\defined('HHVM_VERSION')) {
            self::markTestSkipped("HHVM doesn't support variadics with type declarations.");
        }

        require_once __DIR__ . "/fixtures_5_6.php";

        $injector = new Injector;
        $obj = $injector->make(
            NoTypehintNoDefaultConstructorVariadicClass::class,
            ['val1' => TestDependency::class]
        );
        self::assertInstanceOf(NoTypehintNoDefaultConstructorVariadicClass::class, $obj);
        self::assertEquals([], $obj->testParam);
    }

    /**
     * @requires PHP 5.6
     */
    public function testMakeInstanceUsesReflectionForUnknownParamsWithDepsAndVariadicsWithTypeHint(): void
    {
        if (\defined('HHVM_VERSION')) {
            self::markTestSkipped("HHVM doesn't support variadics with type declarations.");
        }

        require_once __DIR__ . "/fixtures_5_6.php";

        $injector = new Injector;
        $obj = $injector->make(
            TypehintNoDefaultConstructorVariadicClass::class,
            ['arg' => TestDependency::class]
        );
        self::assertInstanceOf(TypehintNoDefaultConstructorVariadicClass::class, $obj);
        self::assertIsArray($obj->testParam);
        self::assertInstanceOf(TestDependency::class, $obj->testParam[0]);
    }

    public function testMakeInstanceThrowsExceptionOnUntypehintedParameterWithoutDefinitionOrDefault(): void
    {
        $injector = new Injector;

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('No definition available to provision typeless parameter $val at position 0 in Amp\Injector\InjectorTestCtorParamWithNoTypehintOrDefault::__construct() declared in Amp\Injector\InjectorTestCtorParamWithNoTypehintOrDefault::');
        $this->expectExceptionCode(Injector::E_UNDEFINED_PARAM);

        $injector->make(InjectorTestCtorParamWithNoTypehintOrDefault::class);
    }

    public function testMakeInstanceThrowsExceptionOnUntypehintedParameterWithoutDefinitionOrDefaultThroughAliasedTypehint(
    ): void
    {
        $injector = new Injector;
        $injector->alias(TestNoExplicitDefine::class, InjectorTestCtorParamWithNoTypehintOrDefault::class);

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('No definition available to provision typeless parameter $val at position 0 in Amp\Injector\InjectorTestCtorParamWithNoTypehintOrDefault::__construct() declared in Amp\Injector\InjectorTestCtorParamWithNoTypehintOrDefault::');
        $this->expectExceptionCode(Injector::E_UNDEFINED_PARAM);

        $injector->make(InjectorTestCtorParamWithNoTypehintOrDefaultDependent::class);
    }

    public function testMakeInstanceThrowsExceptionOnUninstantiableTypehintWithoutDefinition(): void
    {
        $injector = new Injector;

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Injection definition required for interface Amp\Injector\DepInterface');

        $injector->make(RequiresInterface::class);
    }

    public function testTypelessDefineForDependency(): void
    {
        $injector = new Injector;
        $injector->defineParam('thumbnailSize', 128);
        $testClass = $injector->make(RequiresDependencyWithTypelessParameters::class);
        self::assertEquals(128, $testClass->getThumbnailSize(), 'Typeless define was not injected correctly.');
    }

    public function testTypelessDefineForAliasedDependency(): void
    {
        $injector = new Injector;
        $injector->defineParam('val', 42);

        $injector->alias(TestNoExplicitDefine::class, ProviderTestCtorParamWithNoTypehintOrDefault::class);
        $injector->make(ProviderTestCtorParamWithNoTypehintOrDefaultDependent::class);

        $this->expectNotToPerformAssertions();
    }

    public function testMakeInstanceInjectsRawParametersDirectly(): void
    {
        $injector = new Injector;
        $injector->define(InjectorTestRawCtorParams::class, [
            ':string' => 'string',
            ':obj' => new \StdClass,
            ':int' => 42,
            ':array' => [],
            ':float' => 9.3,
            ':bool' => true,
            ':null' => null,
        ]);

        $obj = $injector->make(InjectorTestRawCtorParams::class);
        self::assertIsString($obj->string);
        self::assertInstanceOf('StdClass', $obj->obj);
        self::assertIsInt($obj->int);
        self::assertIsArray($obj->array);
        self::assertIsFloat($obj->float);
        self::assertIsBool($obj->bool);
        self::assertNull($obj->null);
    }

    public function testMakeInstanceThrowsExceptionWhenDelegateDoes(): void
    {
        $injector = new Injector;

        $callable = $this->createMock(CallableMock::class);

        $injector->delegate(TestDependency::class, $callable);

        $callable->expects(self::once())
            ->method('__invoke')
            ->will(self::throwException(new \Exception('test exception')));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('test exception');

        $injector->make(TestDependency::class);
    }

    public function testMakeInstanceHandlesNamespacedClasses(): void
    {
        $injector = new Injector;
        $injector->make(SomeClassName::class);

        $this->expectNotToPerformAssertions();
    }

    public function testMakeInstanceDelegate(): void
    {
        $injector = new Injector;

        $callable = $this->createMock(CallableMock::class);

        $callable->expects(self::once())
            ->method('__invoke')
            ->willReturn(new TestDependency);

        $injector->delegate(TestDependency::class, $callable);

        $obj = $injector->make(TestDependency::class);

        self::assertInstanceOf(TestDependency::class, $obj);
    }

    public function testMakeInstanceWithStringDelegate(): void
    {
        $injector = new Injector;
        $injector->delegate('StdClass', StringStdClassDelegateMock::class);
        $obj = $injector->make('StdClass');
        self::assertEquals(42, $obj->test);
    }

    public function testMakeInstanceThrowsExceptionIfStringDelegateClassHasNoInvokeMethod(): void
    {
        $injector = new Injector;

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Amp\Injector\Injector::delegate expects a valid callable or executable class::method string at Argument 2 but received \'StringDelegateWithNoInvokeMethod\'');

        $injector->delegate('StdClass', 'StringDelegateWithNoInvokeMethod');
    }

    public function testMakeInstanceThrowsExceptionIfStringDelegateClassInstantiationFails(): void
    {
        $injector = new Injector;

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Amp\Injector\Injector::delegate expects a valid callable or executable class::method string at Argument 2 but received \'SomeClassThatDefinitelyDoesNotExistForReal\'');

        $injector->delegate('StdClass', 'SomeClassThatDefinitelyDoesNotExistForReal');
    }

    public function testMakeInstanceThrowsExceptionOnUntypehintedParameterWithNoDefinition(): void
    {
        $injector = new Injector;

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Injection definition required for interface Amp\Injector\DepInterface');

        $injector->make(RequiresInterface::class);
    }

    public function testDefineAssignsPassedDefinition(): void
    {
        $injector = new Injector;
        $definition = ['dep' => DepImplementation::class];
        $injector->define(RequiresInterface::class, $definition);
        self::assertInstanceOf(RequiresInterface::class, $injector->make(RequiresInterface::class));
    }

    public function testShareStoresSharedInstanceAndReturnsCurrentInstance(): void
    {
        $injector = new Injector;
        $testShare = new \StdClass;
        $testShare->test = 42;

        self::assertInstanceOf(Injector::class, $injector->share($testShare));
        $testShare->test = 'test';
        self::assertEquals('test', $injector->make('stdclass')->test);
    }

    public function testShareMarksClassSharedOnNullObjectParameter(): void
    {
        $injector = new Injector;
        self::assertInstanceOf(Injector::class, $injector->share('SomeClass'));
    }

    public function testAliasAssignsValueAndReturnsCurrentInstance(): void
    {
        $injector = new Injector;
        self::assertInstanceOf(Injector::class, $injector->alias('DepInterface', DepImplementation::class));
    }

    public function provideInvalidDelegates(): array
    {
        return [
            [new \StdClass],
            [42],
            [true],
        ];
    }

    /**
     * @dataProvider provideInvalidDelegates
     */
    public function testDelegateThrowsExceptionIfDelegateIsNotCallableOrString($badDelegate): void
    {
        $injector = new Injector;

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Amp\Injector\Injector::delegate expects a valid callable or executable class::method string at Argument 2');

        $injector->delegate(TestDependency::class, $badDelegate);
    }

    public function testDelegateInstantiatesCallableClassString(): void
    {
        $injector = new Injector;
        $injector->delegate(MadeByDelegate::class, CallableDelegateClassTest::class);
        self::assertInstanceof(MadeByDelegate::class, $injector->make(MadeByDelegate::class));
    }

    public function testDelegateInstantiatesCallableClassArray(): void
    {
        $injector = new Injector;
        $injector->delegate(MadeByDelegate::class, [CallableDelegateClassTest::class, '__invoke']);
        self::assertInstanceof(MadeByDelegate::class, $injector->make(MadeByDelegate::class));
    }

    public function testUnknownDelegationFunction(): void
    {
        $injector = new Injector;

        $this->expectException(InjectorException::class);
        $this->expectExceptionMessage('FunctionWhichDoesNotExist');
        $this->expectExceptionCode(Injector::E_DELEGATE_ARGUMENT);

        $injector->delegate(DelegatableInterface::class, 'FunctionWhichDoesNotExist');
    }

    public function testUnknownDelegationMethod(): void
    {
        $injector = new Injector;

        $this->expectException(InjectorException::class);
        $this->expectExceptionMessage('stdClass');
        $this->expectExceptionCode(Injector::E_DELEGATE_ARGUMENT);

        $injector->delegate(DelegatableInterface::class, [\stdClass::class, 'methodWhichDoesNotExist']);
    }

    /**
     * @dataProvider provideExecutionExpectations
     */
    public function testProvisionedInvokables($toInvoke, $definition, $expectedResult): void
    {
        $injector = new Injector;
        self::assertEquals($expectedResult, $injector->execute($toInvoke, $definition));
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

    public function testStaticStringInvokableWithArgument(): void
    {
        $injector = new Injector;
        $invokable = $injector->buildExecutable('Amp\Injector\ClassWithStaticMethodThatTakesArg::doSomething');
        self::assertEquals(42, $invokable(41));
    }

    public function testInterfaceFactoryDelegation(): void
    {
        $injector = new Injector;
        $injector->delegate(DelegatableInterface::class, ImplementsInterfaceFactory::class);

        $requiresDelegatedInterface = $injector->make(RequiresDelegatedInterface::class);
        $requiresDelegatedInterface->foo();

        $this->expectNotToPerformAssertions();
    }

    public function testMissingAlias(): void
    {
        $injector = new Injector;

        $this->expectException(InjectorException::class);
        $this->expectExceptionMessage('Could not make Amp\Injector\TypoInTypehint: Class "Amp\Injector\TypoInTypehint" does not exist');

        $injector->make(TestMissingDependency::class);
    }

    public function testAliasingConcreteClasses(): void
    {
        $injector = new Injector;
        $injector->alias(ConcreteClass1::class, ConcreteClass2::class);
        $obj = $injector->make(ConcreteClass1::class);
        self::assertInstanceOf(ConcreteClass2::class, $obj);
    }

    public function testSharedByAliasedInterfaceName(): void
    {
        $injector = new Injector;
        $injector->alias(SharedAliasedInterface::class, SharedClass::class);
        $injector->share(SharedAliasedInterface::class);
        $class = $injector->make(SharedAliasedInterface::class);
        $class2 = $injector->make(SharedAliasedInterface::class);
        self::assertSame($class, $class2);
    }

    public function testNotSharedByAliasedInterfaceName(): void
    {
        $injector = new Injector;
        $injector->alias(SharedAliasedInterface::class, SharedClass::class);
        $injector->alias(SharedAliasedInterface::class, NotSharedClass::class);
        $injector->share(SharedClass::class);
        $class = $injector->make(SharedAliasedInterface::class);
        $class2 = $injector->make(SharedAliasedInterface::class);

        self::assertNotSame($class, $class2);
    }

    public function testSharedByAliasedInterfaceNameReversedOrder(): void
    {
        $injector = new Injector;
        $injector->share(SharedAliasedInterface::class);
        $injector->alias(SharedAliasedInterface::class, SharedClass::class);
        $class = $injector->make(SharedAliasedInterface::class);
        $class2 = $injector->make(SharedAliasedInterface::class);
        self::assertSame($class, $class2);
    }

    public function testSharedByAliasedInterfaceNameWithParameter(): void
    {
        $injector = new Injector;
        $injector->alias(SharedAliasedInterface::class, SharedClass::class);
        $injector->share(SharedAliasedInterface::class);
        $sharedClass = $injector->make(SharedAliasedInterface::class);
        $childClass = $injector->make(ClassWithAliasAsParameter::class);
        self::assertSame($sharedClass, $childClass->sharedClass);
    }

    public function testSharedByAliasedInstance(): void
    {
        $injector = new Injector;
        $injector->alias(SharedAliasedInterface::class, SharedClass::class);
        $sharedClass = $injector->make(SharedAliasedInterface::class);
        $injector->share($sharedClass);
        $childClass = $injector->make(ClassWithAliasAsParameter::class);
        self::assertSame($sharedClass, $childClass->sharedClass);
    }

    public function testMultipleShareCallsDontOverrideTheOriginalSharedInstance(): void
    {
        $injector = new Injector;
        $injector->share('StdClass');
        $stdClass1 = $injector->make('StdClass');
        $injector->share('StdClass');
        $stdClass2 = $injector->make('StdClass');
        self::assertSame($stdClass1, $stdClass2);
    }

    public function testDependencyWhereSharedWithProtectedConstructor(): void
    {
        $injector = new Injector;

        $inner = TestDependencyWithProtectedConstructor::create();
        $injector->share($inner);

        $outer = $injector->make(TestNeedsDepWithProtCons::class);

        self::assertSame($inner, $outer->dep);
    }

    public function testDependencyWhereShared(): void
    {
        $injector = new Injector;
        $injector->share(ClassInnerB::class);
        $innerDep = $injector->make(ClassInnerB::class);
        $inner = $injector->make(ClassInnerA::class);
        self::assertSame($innerDep, $inner->dep);
        $outer = $injector->make(ClassOuter::class);
        self::assertSame($innerDep, $outer->dep->dep);
    }

    public function testBugWithReflectionPoolIncorrectlyReturningBadInfo(): void
    {
        $injector = new Injector;
        $obj = $injector->make(ClassOuter::class);
        self::assertInstanceOf(ClassOuter::class, $obj);
        self::assertInstanceOf(ClassInnerA::class, $obj->dep);
        self::assertInstanceOf(ClassInnerB::class, $obj->dep->dep);
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
        $injector = new Injector;

        $this->expectException(InjectorException::class);
        $this->expectExceptionCode(Injector::E_CYCLIC_DEPENDENCY);

        $injector->make($class);
    }

    public function testNonConcreteDependencyWithDefault(): void
    {
        $injector = new Injector;
        $object = $injector->make(NonConcreteDependencyWithDefaultValue::class);
        self::assertInstanceOf(NonConcreteDependencyWithDefaultValue::class, $object);
        self::assertNull($object->interface);
    }

    public function testNonConcreteDependencyWithDefaultValueThroughAlias(): void
    {
        $injector = new Injector;
        $injector->alias(
            DelegatableInterface::class,
            ImplementsInterface::class
        );

        $class = $injector->make(NonConcreteDependencyWithDefaultValue::class);

        self::assertInstanceOf(NonConcreteDependencyWithDefaultValue::class, $class);
        self::assertInstanceOf(ImplementsInterface::class, $class->interface);
    }

    public function testNonConcreteDependencyWithDefaultValueThroughDelegation(): void
    {
        $injector = new Injector;
        $injector->delegate(DelegatableInterface::class, ImplementsInterfaceFactory::class);

        $class = $injector->make(NonConcreteDependencyWithDefaultValue::class);

        self::assertInstanceOf(NonConcreteDependencyWithDefaultValue::class, $class);
        self::assertInstanceOf(ImplementsInterface::class, $class->interface);
    }

    public function testDependencyWithDefaultValueThroughShare(): void
    {
        $injector = new Injector;
        // Instance is not shared, null default is used for dependency
        $instance = $injector->make(ConcreteDependencyWithDefaultValue::class);
        self::assertNull($instance->dependency);

        // Instance is explicitly shared, $instance is used for dependency
        $injector->share(new \stdClass);
        $instance = $injector->make(ConcreteDependencyWithDefaultValue::class);
        self::assertInstanceOf(\stdClass::class, $instance->dependency);
    }

    public function testShareAfterAliasException(): void
    {
        $injector = new Injector();
        $testClass = new \StdClass();
        $injector->alias('StdClass', 'Amp\Injector\SomeOtherClass');

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Cannot share class stdclass because it is currently aliased to Amp\Injector\SomeOtherClass');
        $this->expectExceptionCode(Injector::E_ALIASED_CANNOT_SHARE);

        $injector->share($testClass);
    }

    public function testShareAfterAliasAliasedClassAllowed(): void
    {
        $injector = new Injector();
        $testClass = new DepImplementation();
        $injector->alias(DepInterface::class, DepImplementation::class);
        $injector->share($testClass);
        $obj = $injector->make(DepInterface::class);
        self::assertInstanceOf(DepImplementation::class, $obj);
    }

    public function testAliasAfterShareByStringAllowed(): void
    {
        $injector = new Injector();
        $injector->share(DepInterface::class);
        $injector->alias(DepInterface::class, DepImplementation::class);
        $obj = $injector->make(DepInterface::class);
        $obj2 = $injector->make(DepInterface::class);
        self::assertInstanceOf(DepImplementation::class, $obj);
        self::assertEquals($obj, $obj2);
    }

    public function testAliasAfterShareBySharingAliasAllowed(): void
    {
        $injector = new Injector();
        $injector->share(DepImplementation::class);
        $injector->alias(DepInterface::class, DepImplementation::class);
        $obj = $injector->make(DepInterface::class);
        $obj2 = $injector->make(DepInterface::class);
        self::assertInstanceOf(DepImplementation::class, $obj);
        self::assertEquals($obj, $obj2);
    }

    public function testAliasAfterShareException(): void
    {
        $injector = new Injector();
        $testClass = new \StdClass();
        $injector->share($testClass);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Cannot alias class stdclass to Amp\Injector\SomeOtherClass because it is currently shared');
        $this->expectExceptionCode(Injector::E_SHARED_CANNOT_ALIAS);

        $injector->alias('StdClass', 'Amp\Injector\SomeOtherClass');
    }

    public function testAppropriateExceptionThrownOnNonPublicConstructor(): void
    {
        $injector = new Injector();

        $this->expectException(InjectorException::class);
        $this->expectExceptionMessage('Cannot instantiate protected/private constructor in class Amp\Injector\HasNonPublicConstructor');
        $this->expectExceptionCode(Injector::E_NON_PUBLIC_CONSTRUCTOR);

        $injector->make(HasNonPublicConstructor::class);
    }

    public function testAppropriateExceptionThrownOnNonPublicConstructorWithArgs(): void
    {
        $injector = new Injector();

        $this->expectException(InjectorException::class);
        $this->expectExceptionMessage('Cannot instantiate protected/private constructor in class Amp\Injector\HasNonPublicConstructorWithArgs');
        $this->expectExceptionCode(Injector::E_NON_PUBLIC_CONSTRUCTOR);

        $injector->make(HasNonPublicConstructorWithArgs::class);
    }

    public function testMakeExecutableFailsOnNonExistentFunction(): void
    {
        $injector = new Injector();

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('nonExistentFunction');
        $this->expectExceptionCode(Injector::E_INVOKABLE);

        $injector->buildExecutable('nonExistentFunction');
    }

    public function testMakeExecutableFailsOnNonExistentInstanceMethod(): void
    {
        $injector = new Injector();
        $object = new \StdClass();

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage("[object(stdClass), 'nonExistentMethod']");
        $this->expectExceptionCode(Injector::E_INVOKABLE);

        $injector->buildExecutable([$object, 'nonExistentMethod']);
    }

    public function testMakeExecutableFailsOnNonExistentStaticMethod(): void
    {
        $injector = new Injector();

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage("StdClass::nonExistentMethod");
        $this->expectExceptionCode(Injector::E_INVOKABLE);

        $injector->buildExecutable(['StdClass', 'nonExistentMethod']);
    }

    public function testMakeExecutableFailsOnClassWithoutInvoke(): void
    {
        $injector = new Injector();
        $object = new \StdClass();

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Invalid invokable: callable or provisional string required');
        $this->expectExceptionCode(Injector::E_INVOKABLE);

        $injector->buildExecutable($object);
    }

    public function testBadAlias(): void
    {
        $injector = new Injector();
        $injector->share(DepInterface::class);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Invalid alias: non-empty string required at arguments 1 and 2');
        $this->expectExceptionCode(Injector::E_NON_EMPTY_STRING_ALIAS);

        $injector->alias(DepInterface::class, '');
    }

    public function testShareNewAlias(): void
    {
        $injector = new Injector();
        $injector->share(DepImplementation::class);
        $injector->alias(DepInterface::class, DepImplementation::class);

        // TODO Assertions
    }

    public function testDefineWithBackslashAndMakeWithoutBackslash(): void
    {
        $injector = new Injector();
        $injector->define(SimpleNoTypehintClass::class, [':arg' => 'tested']);
        $testClass = $injector->make(SimpleNoTypehintClass::class);
        self::assertEquals('tested', $testClass->testParam);
    }

    public function testShareWithBackslashAndMakeWithoutBackslash(): void
    {
        $injector = new Injector();
        $injector->share('\StdClass');
        $classA = $injector->make('StdClass');
        $classA->tested = false;
        $classB = $injector->make('\StdClass');
        $classB->tested = true;

        self::assertEquals($classA->tested, $classB->tested);
    }

    public function testInstanceMutate(): void
    {
        $injector = new Injector();
        $injector->prepare('\StdClass', function ($obj) {
            $obj->testval = 42;
        });
        $obj = $injector->make('StdClass');

        self::assertSame(42, $obj->testval);
    }

    public function testInterfaceMutate(): void
    {
        $injector = new Injector();
        $injector->prepare(SomeInterface::class, function ($obj) {
            $obj->testProp = 42;
        });
        $obj = $injector->make(PreparesImplementationTest::class);

        self::assertSame(42, $obj->testProp);
    }

    /**
     * Test that custom definitions are not passed through to dependencies.
     * Surprising things would happen if this did occur.
     */
    public function testCustomDefinitionNotPassedThrough(): void
    {
        $injector = new Injector();
        $injector->share(DependencyWithDefinedParam::class);

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('No definition available to provision typeless parameter $foo at position 0 in Amp\Injector\DependencyWithDefinedParam::__construct() declared in Amp\Injector\DependencyWithDefinedParam::');
        $this->expectExceptionCode(Injector::E_UNDEFINED_PARAM);

        $injector->make(RequiresDependencyWithDefinedParam::class, [':foo' => 5]);
    }

    public function testDelegationFunction(): void
    {
        $injector = new Injector();
        $injector->delegate(TestDelegationSimple::class, 'Amp\Injector\createTestDelegationSimple');
        $obj = $injector->make(TestDelegationSimple::class);
        self::assertInstanceOf(TestDelegationSimple::class, $obj);
        self::assertTrue($obj->delegateCalled);
    }

    public function testDelegationDependency(): void
    {
        $injector = new Injector();
        $injector->delegate(
            TestDelegationDependency::class,
            'Amp\Injector\createTestDelegationDependency'
        );
        $obj = $injector->make(TestDelegationDependency::class);
        self::assertInstanceOf(TestDelegationDependency::class, $obj);
        self::assertTrue($obj->delegateCalled);
    }

    public function testExecutableAliasing(): void
    {
        $injector = new Injector();
        $injector->alias(BaseExecutableClass::class, ExtendsExecutableClass::class);
        $result = $injector->execute([BaseExecutableClass::class, 'foo']);
        self::assertEquals('This is the ExtendsExecutableClass', $result);
    }

    public function testExecutableAliasingStatic(): void
    {
        $injector = new Injector();
        $injector->alias(BaseExecutableClass::class, ExtendsExecutableClass::class);
        $result = $injector->execute([BaseExecutableClass::class, 'bar']);
        self::assertEquals('This is the ExtendsExecutableClass', $result);
    }

    /**
     * Test coverage for delegate closures that are defined outside of a class.
     */
    public function testDelegateClosure(): void
    {
        $delegateClosure = getDelegateClosureInGlobalScope();
        $injector = new Injector();
        $injector->delegate(DelegateClosureInGlobalScope::class, $delegateClosure);
        $injector->make(DelegateClosureInGlobalScope::class);

        $this->expectNotToPerformAssertions();
    }

    public function testCloningWithServiceLocator(): void
    {
        $injector = new Injector();
        $injector->share($injector);
        $instance = $injector->make(CloneTest::class);
        $newInjector = $instance->injector;
        $newInjector->make(CloneTest::class);

        // TODO Assertion
    }

    public function testAbstractExecute(): void
    {
        $injector = new Injector();

        $fn = function () {
            return new ConcreteExexcuteTest;
        };

        $injector->delegate(AbstractExecuteTest::class, $fn);
        $result = $injector->execute([AbstractExecuteTest::class, 'process']);

        self::assertEquals('Concrete', $result);
    }

    public function testDebugMake(): void
    {
        $injector = new Injector();
        try {
            $injector->make(DependencyChainTest::class);
        } catch (InjectionException $ie) {
            $chain = $ie->getDependencyChain();
            self::assertCount(2, $chain);

            self::assertEquals('amp\injector\dependencychaintest', $chain[0]);
            self::assertEquals('amp\injector\depinterface', $chain[1]);
        }
    }

    public function testInspectShares(): void
    {
        $injector = new Injector();
        $injector->share(SomeClassName::class);

        $inspection = $injector->inspect(SomeClassName::class, Injector::I_SHARES);
        self::assertArrayHasKey('amp\injector\someclassname', $inspection[Injector::I_SHARES]);
    }

    public function testInspectAll(): void
    {
        $injector = new Injector();

        // Injector::I_BINDINGS
        $injector->define(DependencyWithDefinedParam::class, [':arg' => 42]);

        // Injector::I_DELEGATES
        $injector->delegate(MadeByDelegate::class, CallableDelegateClassTest::class);

        // Injector::I_PREPARES
        $injector->prepare(MadeByDelegate::class, function ($c) {
        });

        // Injector::I_ALIASES
        $injector->alias('i', Injector::class);

        // Injector::I_SHARES
        $injector->share(Injector::class);

        $all = $injector->inspect();
        $some = $injector->inspect(MadeByDelegate::class);

        self::assertCount(5, \array_filter($all));
        self::assertCount(2, \array_filter($some));
    }

    public function testDelegationDoesntMakeObject(): void
    {
        $delegate = function () {
            return null;
        };
        $injector = new Injector();
        $injector->delegate(SomeClassName::class, $delegate);

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Making amp\injector\someclassname did not result in an object, instead result is of type \'NULL\'');
        $this->expectExceptionCode(Injector::E_MAKING_FAILED);

        $injector->make(SomeClassName::class);
    }

    public function testDelegationDoesntMakeObjectMakesString(): void
    {
        $injector = new Injector;
        $injector->delegate(SomeClassName::class, fn() => 'ThisIsNotAClass');

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('Making amp\injector\someclassname did not result in an object, instead result is of type \'string\'');
        $this->expectExceptionCode(Injector::E_MAKING_FAILED);

        $injector->make(SomeClassName::class);
    }

    public function testPrepareInvalidCallable(): void
    {
        $injector = new Injector;
        $invalidCallable = 'This_does_not_exist';

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage($invalidCallable);

        $injector->prepare("StdClass", $invalidCallable);
    }

    public function testPrepareCallableReplacesObjectWithReturnValueOfSameInterfaceType(): void
    {
        $injector = new Injector;
        $expected = new SomeImplementation;
        $injector->prepare(SomeInterface::class, function () use ($expected) {
            return $expected;
        });
        $actual = $injector->make(SomeImplementation::class);
        self::assertSame($expected, $actual);
    }

    public function testPrepareCallableReplacesObjectWithReturnValueOfSameClassType(): void
    {
        $injector = new Injector;
        $expected = new SomeImplementation;
        $injector->prepare(SomeImplementation::class, function () use ($expected) {
            return $expected;
        });
        $actual = $injector->make(SomeImplementation::class);
        self::assertSame($expected, $actual);
    }

    public function testChildWithoutConstructorWorks(): void
    {
        $injector = new Injector;
        try {
            $injector->define(ParentWithConstructor::class, [':foo' => 'parent']);
            $injector->define(ChildWithoutConstructor::class, [':foo' => 'child']);

            $injector->share(ParentWithConstructor::class);
            $injector->share(ChildWithoutConstructor::class);

            $child = $injector->make(ChildWithoutConstructor::class);
            self::assertEquals('child', $child->foo);

            $parent = $injector->make(ParentWithConstructor::class);
            self::assertEquals('parent', $parent->foo);
        } catch (InjectionException $ie) {
            echo $ie->getMessage();
            self::fail("Amp\Injector failed to locate the ");
        }
    }

    public function testChildWithoutConstructorMissingParam(): void
    {
        $injector = new Injector;
        $injector->define(ParentWithConstructor::class, [':foo' => 'parent']);

        $this->expectException(InjectionException::class);
        $this->expectExceptionMessage('No definition available to provision typeless parameter $foo at position 0 in Amp\Injector\ChildWithoutConstructor::__construct() declared in Amp\Injector\ParentWithConstructor');
        $this->expectExceptionCode(Injector::E_UNDEFINED_PARAM);

        $injector->make(ChildWithoutConstructor::class);
    }

    public function testInstanceClosureDelegates(): void
    {
        $injector = new Injector;
        $injector->delegate(DelegatingInstanceA::class, function (DelegateA $d) {
            return new DelegatingInstanceA($d);
        });
        $injector->delegate(DelegatingInstanceB::class, function (DelegateB $d) {
            return new DelegatingInstanceB($d);
        });

        $a = $injector->make(DelegatingInstanceA::class);
        $b = $injector->make(DelegatingInstanceB::class);

        self::assertInstanceOf(DelegateA::class, $a->a);
        self::assertInstanceOf(DelegateB::class, $b->b);
    }

    public function testThatExceptionInConstructorDoesntCauseCyclicDependencyException(): void
    {
        $injector = new Injector;

        try {
            $injector->make(ThrowsExceptionInConstructor::class);
        } catch (\Exception) {
            // ignore
        }

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Exception in constructor');

        $injector->make(ThrowsExceptionInConstructor::class);
    }
}
