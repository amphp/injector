<?php

namespace Amp\Injector;

use Amp\Injector\Internal\Executable;

final class Injector
{
    public const A_RAW = ':';
    public const A_DELEGATE = '+';
    public const A_DEFINE = '@';
    public const I_BINDINGS = 1;
    public const I_DELEGATES = 2;
    public const I_PREPARES = 4;
    public const I_ALIASES = 8;
    public const I_SHARES = 16;
    public const I_ALL = 31;

    public const E_NON_EMPTY_STRING_ALIAS = 1;
    public const M_NON_EMPTY_STRING_ALIAS = "Invalid alias: non-empty string required at arguments 1 and 2";
    public const E_SHARED_CANNOT_ALIAS = 2;
    public const M_SHARED_CANNOT_ALIAS = "Cannot alias class %s to %s because it is currently shared";
    public const E_SHARE_ARGUMENT = 3;
    public const M_SHARE_ARGUMENT = "%s::share() requires a string class name or object instance at Argument 1; %s specified";
    public const E_ALIASED_CANNOT_SHARE = 4;
    public const M_ALIASED_CANNOT_SHARE = "Cannot share class %s because it is currently aliased to %s";
    public const E_INVOKABLE = 5;
    public const M_INVOKABLE = "Invalid invokable: callable or provisional string required";
    public const E_NON_PUBLIC_CONSTRUCTOR = 6;
    public const M_NON_PUBLIC_CONSTRUCTOR = "Cannot instantiate protected/private constructor in class %s";
    public const E_NEEDS_DEFINITION = 7;
    public const M_NEEDS_DEFINITION = "Injection definition required for %s %s";
    public const E_MAKE_FAILURE = 8;
    public const M_MAKE_FAILURE = "Could not make %s: %s";
    public const E_UNDEFINED_PARAM = 9;
    public const M_UNDEFINED_PARAM = "No definition available to provision typeless parameter \$%s at position %d in %s()%s";
    public const E_DELEGATE_ARGUMENT = 10;
    public const M_DELEGATE_ARGUMENT = "%s::delegate expects a valid callable or executable class::method string at Argument 2%s";
    public const E_CYCLIC_DEPENDENCY = 11;
    public const M_CYCLIC_DEPENDENCY = "Detected a cyclic dependency while provisioning %s";
    public const E_MAKING_FAILED = 12;
    public const M_MAKING_FAILED = "Making %s did not result in an object, instead result is of type '%s'";

    private Reflector $reflector;
    private array $classDefinitions = [];
    private array $paramDefinitions = [];
    private array $aliases = [];
    private array $shares = [];
    private array $prepares = [];
    private array $delegates = [];
    private array $proxies = [];
    private array $preparesProxy = [];
    private array $inProgressMakes = [];

    public function __construct(?Reflector $reflector = null)
    {
        $this->reflector = $reflector ?: new CachingReflector;
    }

    public function __clone()
    {
        $this->inProgressMakes = [];
    }

    /**
     * Define instantiation directives for the specified class.
     *
     * @param string $name The class (or alias) whose constructor arguments we wish to define
     * @param array  $args An array mapping parameter names to values/instructions
     *
     * @return self
     */
    public function define(string $name, array $args): self
    {
        [, $normalizedName] = $this->resolveAlias($name);
        $this->classDefinitions[$normalizedName] = $args;

        return $this;
    }

    /**
     * Assign a global default value for all parameters named $paramName.
     *
     * Global parameter definitions are only used for parameters with no typehint, pre-defined or
     * call-time definition.
     *
     * @param string $paramName The parameter name for which this value applies
     * @param mixed  $value The value to inject for this parameter name
     *
     * @return self
     */
    public function defineParam(string $paramName, mixed $value): self
    {
        $this->paramDefinitions[$paramName] = $value;

        return $this;
    }

    /**
     * Define an alias for all occurrences of a given typehint.
     *
     * Use this method to specify implementation classes for interface and abstract class typehints.
     *
     * @param string $original The typehint to replace
     * @param string $alias The implementation name
     *
     * @return self
     * @throws ConfigException if any argument is empty
     */
    public function alias(string $original, string $alias): self
    {
        if ($original === '' || $alias === '') {
            throw new ConfigException(self::M_NON_EMPTY_STRING_ALIAS, self::E_NON_EMPTY_STRING_ALIAS);
        }

        $originalNormalized = $this->normalizeName($original);

        if (isset($this->shares[$originalNormalized])) {
            throw new ConfigException(
                \sprintf(
                    self::M_SHARED_CANNOT_ALIAS,
                    $this->normalizeName(\get_class($this->shares[$originalNormalized])),
                    $alias
                ),
                self::E_SHARED_CANNOT_ALIAS
            );
        }

        if (\array_key_exists($originalNormalized, $this->shares)) {
            $aliasNormalized = $this->normalizeName($alias);
            $this->shares[$aliasNormalized] = null;
            unset($this->shares[$originalNormalized]);
        }

        $this->aliases[$originalNormalized] = $alias;

        return $this;
    }

    /**
     * Share the specified class/instance across the Injector context.
     *
     * @param mixed $nameOrInstance The class or object to share
     *
     * @return self
     * @throws ConfigException if $nameOrInstance is not a string or an object
     */
    public function share(string|object $nameOrInstance): self
    {
        if (\is_string($nameOrInstance)) {
            $this->shareClass($nameOrInstance);
        } else {
            $this->shareInstance($nameOrInstance);
        }

        return $this;
    }

    /**
     * Register a prepare callable to modify/prepare objects of type $name after instantiation.
     *
     * Any callable or provisionable invokable may be specified. Preparers are passed two
     * arguments: the instantiated object to be mutated and the current Injector instance.
     *
     * @param string $name
     * @param mixed  $callableOrMethodStr Any callable or provisionable invokable method
     *
     * @return self
     * @throws InjectionException if $callableOrMethodStr is not a callable.
     *                            See https://github.com/amphp/injector#injecting-for-execution
     */
    public function prepare(string $name, mixed $callableOrMethodStr): self
    {
        if ($this->isExecutable($callableOrMethodStr) === false) {
            throw InjectionException::fromInvalidCallable(
                $this->inProgressMakes,
                $callableOrMethodStr
            );
        }

        [, $normalizedName] = $this->resolveAlias($name);
        $this->prepares[$normalizedName] = $callableOrMethodStr;

        return $this;
    }

    /**
     * Delegate the creation of $name instances to the specified callable, receiving arguments based on the callables
     * signature.
     *
     * @param string $name
     * @param mixed  $callableOrMethodStr Any callable or provisionable invokable method
     *
     * @return self
     * @throws ConfigException if $callableOrMethodStr is not a callable.
     */
    public function delegate(string $name, mixed $callableOrMethodStr): self
    {
        if (!$this->isExecutable($callableOrMethodStr)) {
            $this->generateInvalidCallableError($callableOrMethodStr);
        }

        $normalizedName = $this->normalizeName($name);
        $this->delegates[$normalizedName] = $callableOrMethodStr;

        return $this;
    }

    /**
     * Retrieve stored data for the specified definition type.
     *
     * Exposes introspection of existing binds/delegates/shares/etc for decoration and composition.
     *
     * @param string|null $nameFilter An optional class name filter
     * @param int|null    $typeFilter A bitmask of Injector::* type constant flags
     *
     * @return array
     */
    public function inspect(?string $nameFilter = null, ?int $typeFilter = null): array
    {
        $result = [];
        $name = $nameFilter ? $this->normalizeName($nameFilter) : null;

        if ($typeFilter === null) {
            $typeFilter = self::I_ALL;
        }

        $types = [
            self::I_BINDINGS => "classDefinitions",
            self::I_DELEGATES => "delegates",
            self::I_PREPARES => "prepares",
            self::I_ALIASES => "aliases",
            self::I_SHARES => "shares",
        ];

        foreach ($types as $type => $source) {
            if ($typeFilter & $type) {
                $result[$type] = $this->filter($this->{$source}, $name);
            }
        }

        return $result;
    }

    /**
     * Proxy the specified class.
     *
     * @param string $name The class to proxy.
     * @param mixed  $callableOrMethodStr
     *
     * @return Injector
     * @throws ConfigException
     */
    public function proxy(string $name, $callableOrMethodStr)
    {
        if (!$this->isExecutable($callableOrMethodStr)) {
            $this->generateInvalidCallableError($callableOrMethodStr);
        }

        [, $normalizedName] = $this->resolveAlias($name);
        $this->proxies[$normalizedName] = $callableOrMethodStr;

        return $this;
    }

    /**
     * Instantiate/provision a class instance.
     *
     * @param string $name
     * @param array  $args
     *
     * @return mixed
     * @throws InjectionException if a cyclic gets detected when provisioning
     */
    public function make(string $name, array $args = []): mixed
    {
        [$className, $normalizedClass] = $this->resolveAlias($name);

        if (isset($this->inProgressMakes[$normalizedClass])) {
            throw new InjectionException(
                $this->inProgressMakes,
                \sprintf(
                    self::M_CYCLIC_DEPENDENCY,
                    $className
                ),
                self::E_CYCLIC_DEPENDENCY
            );
        }

        $this->inProgressMakes[$normalizedClass] = \count($this->inProgressMakes);

        // isset() is used specifically here because classes may be marked as "shared" before an
        // instance is stored. In these cases the class is "shared," but it has a null value and
        // instantiation is needed.
        if (isset($this->shares[$normalizedClass])) {
            unset($this->inProgressMakes[$normalizedClass]);

            return $this->shares[$normalizedClass];
        }

        try {
            if (isset($this->delegates[$normalizedClass])) {
                $executable = $this->buildExecutable($this->delegates[$normalizedClass]);
                $arguments = $this->provisionArguments($executable->getCallable(), $args, null, $className);

                $object = $executable(...$arguments);

                if (!$object instanceof $normalizedClass) {
                    throw new InjectionException($this->inProgressMakes, \sprintf(
                        self::M_MAKING_FAILED,
                        $normalizedClass,
                        \gettype($object)
                    ), self::E_MAKING_FAILED);
                }
            } elseif (isset($this->proxies[$normalizedClass])) {
                if (isset($this->prepares[$normalizedClass])) {
                    $this->preparesProxy[$normalizedClass] = $this->prepares[$normalizedClass];
                }

                $object = $this->resolveProxy($className, $normalizedClass, $args);

                unset($this->prepares[$normalizedClass]);
            } else {
                $object = $this->provisionInstance($className, $normalizedClass, $args);
            }

            $object = $this->prepareInstance($object, $normalizedClass);

            if (\array_key_exists($normalizedClass, $this->shares)) {
                $this->shares[$normalizedClass] = $object;
            }
        } finally {
            unset($this->inProgressMakes[$normalizedClass]);
        }

        return $object;
    }

    /**
     * Invoke the specified callable or class::method string, provisioning dependencies along the way.
     *
     * @param mixed $callableOrMethodStr A valid PHP callable or a provisionable ClassName::methodName string
     * @param array $arguments Optional array specifying params with which to invoke the provisioned callable
     *
     * @return mixed Returns the invocation result returned from calling the generated executable
     * @throws InjectionException
     */
    public function execute(mixed $callableOrMethodStr, array $arguments = []): mixed
    {
        [$callable, $object] = $this->buildExecutableStruct($callableOrMethodStr);

        $executable = new Executable($callable, $object);
        $arguments = $this->provisionArguments(
            $callable,
            $arguments,
            null,
            $object === null ? null : \get_class($object)
        );

        return $executable(...$arguments);
    }

    /**
     * Provision an Executable instance from any valid callable or class::method string.
     *
     * @param mixed $callableOrMethodStr A valid PHP callable or a provisionable ClassName::methodName string
     *
     * @return Executable
     * @throws InjectionException
     */
    public function buildExecutable(mixed $callableOrMethodStr): Executable
    {
        try {
            [$reflectionCallable, $object] = $this->buildExecutableStruct($callableOrMethodStr);

            return new Executable($reflectionCallable, $object);
        } catch (\ReflectionException $e) {
            throw InjectionException::fromInvalidCallable(
                $this->inProgressMakes,
                $callableOrMethodStr,
                $e
            );
        }
    }

    private function normalizeName(string $className): string
    {
        return \strtolower(\ltrim($className, '\\?'));
    }

    private function shareClass(string|object $nameOrInstance): void
    {
        [, $normalizedName] = $this->resolveAlias($nameOrInstance);
        $this->shares[$normalizedName] = $this->shares[$normalizedName] ?? null;
    }

    private function resolveAlias($name): array
    {
        $normalizedName = $this->normalizeName($name);
        if (isset($this->aliases[$normalizedName])) {
            $name = $this->aliases[$normalizedName];
            $normalizedName = $this->normalizeName($name);
        }

        return [$name, $normalizedName];
    }

    /**
     * @param object $instance
     *
     * @throws ConfigException
     */
    private function shareInstance(object $instance): void
    {
        $normalizedName = $this->normalizeName(\get_class($instance));
        if (isset($this->aliases[$normalizedName])) {
            // You cannot share an instance of a class name that is already aliased
            throw new ConfigException(
                \sprintf(
                    self::M_ALIASED_CANNOT_SHARE,
                    $normalizedName,
                    $this->aliases[$normalizedName]
                ),
                self::E_ALIASED_CANNOT_SHARE
            );
        }

        $this->shares[$normalizedName] = $instance;
    }

    private function resolveProxy(string $className, string $normalizedClass, array $args)
    {
        $callback = function () use ($className, $normalizedClass, $args) {
            return $this->buildWrappedObject($className, $normalizedClass, $args);
        };

        $proxy = $this->proxies[$normalizedClass];

        return $proxy($className, $callback);
    }

    /**
     * @param string $className
     * @param string $normalizedClass
     * @param array  $args
     *
     * @return object
     * @throws InjectionException
     */
    private function buildWrappedObject(string $className, string $normalizedClass, array $args): object
    {
        $wrappedObject = $this->provisionInstance($className, $normalizedClass, $args);

        if (isset($this->preparesProxy[$normalizedClass])) {
            $this->prepares[$normalizedClass] = $this->preparesProxy[$normalizedClass];
        }

        return $this->prepareInstance($wrappedObject, $normalizedClass);
    }

    /**
     * @param $callableOrMethodStr
     *
     * @throws ConfigException
     */
    private function generateInvalidCallableError($callableOrMethodStr): void
    {
        $errorDetail = '';
        if (\is_string($callableOrMethodStr)) {
            $errorDetail = " but received '$callableOrMethodStr'";
        } elseif (\is_array($callableOrMethodStr) &&
            \count($callableOrMethodStr) === 2 &&
            \array_key_exists(0, $callableOrMethodStr) &&
            \array_key_exists(1, $callableOrMethodStr)
        ) {
            if (\is_string($callableOrMethodStr[0]) && \is_string($callableOrMethodStr[1])) {
                $errorDetail = " but received ['" . $callableOrMethodStr[0] . "', '" . $callableOrMethodStr[1] . "']";
            }
        }

        throw new ConfigException(
            \sprintf(self::M_DELEGATE_ARGUMENT, __CLASS__, $errorDetail),
            self::E_DELEGATE_ARGUMENT
        );
    }

    private function isExecutable(mixed $callable): bool
    {
        if (\is_callable($callable)) {
            return true;
        }

        if (\is_string($callable) && \method_exists($callable, '__invoke')) {
            return true;
        }

        if (\is_array($callable) && isset($callable[0], $callable[1]) && \method_exists($callable[0], $callable[1])) {
            return true;
        }

        return false;
    }

    private function filter(array $source, ?string $name): array
    {
        if ($name === null) {
            return $source;
        }

        if (\array_key_exists($name, $source)) {
            return [$name => $source[$name]];
        }

        return [];
    }

    /**
     * @param string $className
     * @param string $normalizedClass
     * @param array  $definition
     *
     * @return object
     * @throws InjectionException
     */
    private function provisionInstance(string $className, string $normalizedClass, array $definition): object
    {
        try {
            $constructor = $this->reflector->getCtor($className);

            if (!$constructor) {
                $object = $this->instantiateWithoutConstructorParameters($className);
            } elseif (!$constructor->isPublic()) {
                throw new InjectionException(
                    $this->inProgressMakes,
                    \sprintf(self::M_NON_PUBLIC_CONSTRUCTOR, $className),
                    self::E_NON_PUBLIC_CONSTRUCTOR
                );
            } elseif ($constructorParameters = $this->reflector->getCtorParams($className)) {
                $class = $this->reflector->getClass($className);
                $definition = isset($this->classDefinitions[$normalizedClass])
                    ? \array_replace($this->classDefinitions[$normalizedClass], $definition)
                    : $definition;
                $arguments = $this->provisionArguments($constructor, $definition, $constructorParameters, $className);
                $object = $class->newInstanceArgs($arguments);
            } else {
                $object = $this->instantiateWithoutConstructorParameters($className);
            }

            return $object;
        } catch (\ReflectionException $e) {
            throw new InjectionException(
                $this->inProgressMakes,
                \sprintf(self::M_MAKE_FAILURE, $className, $e->getMessage()),
                self::E_MAKE_FAILURE,
                $e
            );
        }
    }

    /**
     * @param class-string $className
     *
     * @return object
     * @throws InjectionException
     */
    private function instantiateWithoutConstructorParameters(string $className): object
    {
        $class = $this->reflector->getClass($className);

        if (!$class->isInstantiable()) {
            $type = $class->isInterface() ? 'interface' : 'abstract class';
            throw new InjectionException(
                $this->inProgressMakes,
                \sprintf(self::M_NEEDS_DEFINITION, $type, $className),
                self::E_NEEDS_DEFINITION
            );
        }

        return new $className;
    }

    private function provisionArguments(
        \ReflectionFunctionAbstract $reflectionCallable,
        array $definition,
        ?array $reflectionParams = null,
        ?string $className = null
    ): array {
        $args = [];

        // @TODO store this in ReflectionStorage
        if ($reflectionParams === null) {
            $reflectionParams = $reflectionCallable->getParameters();
        }

        foreach ($reflectionParams as $i => $reflectionParam) {
            $name = $reflectionParam->name;

            if (\array_key_exists($i, $definition)) {
                // indexed arguments take precedence over named parameters
                $arg = $definition[$i];
            } elseif (\array_key_exists($name, $definition)) {
                // interpret the param as a class name to be instantiated
                $arg = $this->make($definition[$name]);
            } elseif (($prefix = self::A_RAW . $name) && \array_key_exists($prefix, $definition)) {
                // interpret the param as a raw value to be injected
                $arg = $definition[$prefix];
            } elseif (($prefix = self::A_DELEGATE . $name) && isset($definition[$prefix])) {
                // interpret the param as an invokable delegate
                $arg = $this->buildArgumentFromDelegate($name, $definition[$prefix]);
            } elseif (($prefix = self::A_DEFINE . $name) && isset($definition[$prefix])) {
                // interpret the param as a class definition
                $arg = $this->buildArgumentFromParamDefineArray($definition[$prefix]);
            } elseif (!$arg = $this->buildArgumentFromTypeDeclaration($reflectionCallable, $reflectionParam)) {
                $arg = $this->buildArgumentFromReflectionParameter($reflectionParam, $className);

                if ($arg === null && $reflectionParam->isVariadic()) {
                    // buildArgumentFromReflectionParameter might return null in case the parameter is optional
                    // in case of variadics, the parameter is optional, but null might not be allowed
                    continue;
                }
            }

            $args[] = $arg;
        }

        return $args;
    }

    private function buildArgumentFromParamDefineArray($definition)
    {
        if (!\is_array($definition)) {
            throw new InjectionException(
                $this->inProgressMakes
            // @TODO Add message
            );
        }

        if (!isset($definition[0], $definition[1])) {
            throw new InjectionException(
                $this->inProgressMakes
            // @TODO Add message
            );
        }

        [$class, $definition] = $definition;

        return $this->make($class, $definition);
    }

    private function buildArgumentFromDelegate($paramName, $callableOrMethodStr)
    {
        if ($this->isExecutable($callableOrMethodStr) === false) {
            throw InjectionException::fromInvalidCallable(
                $this->inProgressMakes,
                $callableOrMethodStr
            );
        }

        $executable = $this->buildExecutable($callableOrMethodStr);

        return $executable($paramName, $this);
    }

    private function buildArgumentFromTypeDeclaration(
        \ReflectionFunctionAbstract $callable,
        \ReflectionParameter $parameter
    ) {
        $type = $this->reflector->getParamTypeHint($callable, $parameter);

        if (!$type) {
            $value = null;
        } elseif ($parameter->isDefaultValueAvailable()) {
            $normalizedName = $this->normalizeName($type);

            // Injector has been told explicitly how to make this type
            if (isset($this->aliases[$normalizedName]) || isset($this->delegates[$normalizedName]) || isset($this->shares[$normalizedName])) {
                $value = $this->make($type);
            } else {
                $value = $parameter->getDefaultValue();
            }
        } else {
            $value = $this->make($type);
        }

        return $value;
    }

    private function buildArgumentFromReflectionParameter(\ReflectionParameter $parameter, ?string $className = null)
    {
        if (\array_key_exists($parameter->name, $this->paramDefinitions)) {
            $arg = $this->paramDefinitions[$parameter->name];
        } elseif ($parameter->isDefaultValueAvailable()) {
            $arg = $parameter->getDefaultValue();
        } elseif ($parameter->isOptional()) {
            // This branch is required to work around PHP bugs where a parameter is optional
            // but has no default value available through reflection. Specifically, PDO exhibits
            // this behavior.
            $arg = null;
        } else {
            $callable = $parameter->getDeclaringFunction();
            $classDeclare = ($callable instanceof \ReflectionMethod)
                ? " declared in " . $callable->getDeclaringClass()->name . "::"
                : "";
            $classWord = ($callable instanceof \ReflectionMethod)
                ? $className . '::'
                : '';
            $funcWord = $classWord . $callable->name;

            throw new InjectionException(
                $this->inProgressMakes,
                \sprintf(
                    self::M_UNDEFINED_PARAM,
                    $parameter->name,
                    $parameter->getPosition(),
                    $funcWord,
                    $classDeclare
                ),
                self::E_UNDEFINED_PARAM
            );
        }

        return $arg;
    }

    private function prepareInstance(object $object, string $normalizedClass): object
    {
        if (isset($this->prepares[$normalizedClass])) {
            $prepare = $this->prepares[$normalizedClass];
            $executable = $this->buildExecutable($prepare);
            $result = $executable($object, $this);
            if ($result instanceof $normalizedClass) {
                $object = $result;
            } else {
                if ($result !== null) {
                    throw new InjectionException($this->inProgressMakes, \sprintf(
                        self::M_MAKING_FAILED,
                        $normalizedClass,
                        \gettype($result)
                    ), self::E_MAKING_FAILED);
                }
            }
        }

        $interfaces = @\class_implements($object);

        if ($interfaces === false) {
            throw new InjectionException($this->inProgressMakes, \sprintf(
                self::M_MAKING_FAILED,
                $normalizedClass,
                \gettype($object)
            ), self::E_MAKING_FAILED);
        }

        if (empty($interfaces)) {
            return $object;
        }

        $interfaces = \array_flip(\array_map(fn ($name) => $this->normalizeName($name), $interfaces));
        $prepares = \array_intersect_key($this->prepares, $interfaces);
        foreach ($prepares as $interfaceName => $prepare) {
            $executable = $this->buildExecutable($prepare);
            $result = $executable($object, $this);
            if ($result instanceof $normalizedClass) {
                $object = $result;
            } elseif ($result !== null) {
                throw new InjectionException($this->inProgressMakes, \sprintf(
                    self::M_MAKING_FAILED,
                    $normalizedClass,
                    \gettype($result)
                ), self::E_MAKING_FAILED);
            }
        }

        return $object;
    }

    private function buildExecutableStruct($callableOrMethodStr): array
    {
        if (\is_string($callableOrMethodStr)) {
            $executableStruct = $this->buildExecutableStructFromString($callableOrMethodStr);
        } elseif ($callableOrMethodStr instanceof \Closure) {
            $callable = new \ReflectionFunction($callableOrMethodStr);
            $executableStruct = [$callable, null];
        } elseif (\is_object($callableOrMethodStr) && \is_callable($callableOrMethodStr)) {
            $invocationObj = $callableOrMethodStr;
            $callable = $this->reflector->getMethod($invocationObj, '__invoke');
            $executableStruct = [$callable, $invocationObj];
        } elseif (\is_array($callableOrMethodStr)
            && isset($callableOrMethodStr[0], $callableOrMethodStr[1])
            && \count($callableOrMethodStr) === 2
        ) {
            $executableStruct = $this->buildExecutableStructFromArray($callableOrMethodStr);
        } else {
            throw InjectionException::fromInvalidCallable(
                $this->inProgressMakes,
                $callableOrMethodStr
            );
        }

        return $executableStruct;
    }

    private function buildExecutableStructFromString(string $executable): array
    {
        if (\function_exists($executable)) {
            $executableStruct = [$this->reflector->getFunction($executable), null];
        } elseif (\method_exists($executable, '__invoke')) {
            $object = $this->make($executable);
            $callable = $this->reflector->getMethod($object, '__invoke');
            $executableStruct = [$callable, $object];
        } elseif (\str_contains($executable, '::')) {
            [$class, $method] = \explode('::', $executable, 2);
            $executableStruct = $this->buildStringClassMethodCallable($class, $method);
        } else {
            throw InjectionException::fromInvalidCallable(
                $this->inProgressMakes,
                $executable
            );
        }

        return $executableStruct;
    }

    private function buildStringClassMethodCallable(string $class, string $method): array
    {
        $relativeStaticMethodStartPos = \strpos($method, 'parent::');

        if ($relativeStaticMethodStartPos === 0) {
            $childReflection = $this->reflector->getClass($class);
            $class = $childReflection->getParentClass()->name;
            $method = \substr($method, $relativeStaticMethodStartPos + 8);
        }

        [$className] = $this->resolveAlias($class);
        $reflectionMethod = $this->reflector->getMethod($className, $method);

        if ($reflectionMethod->isStatic()) {
            return [$reflectionMethod, null];
        }

        $instance = $this->make($className);
        // If the class was delegated, the instance may not be of the type
        // $class but some other type. We need to get the reflection on the
        // actual class to be able to call the method correctly.
        $reflectionMethod = $this->reflector->getMethod($instance, $method);

        return [$reflectionMethod, $instance];
    }

    private function buildExecutableStructFromArray(array $arrayExecutable): array
    {
        [$classOrInstance, $method] = $arrayExecutable;

        if (\is_object($classOrInstance) && \method_exists($classOrInstance, $method)) {
            $executableStruct = [$this->reflector->getMethod($classOrInstance, $method), $classOrInstance];
        } elseif (\is_string($classOrInstance)) {
            $executableStruct = $this->buildStringClassMethodCallable($classOrInstance, $method);
        } else {
            throw InjectionException::fromInvalidCallable(
                $this->inProgressMakes,
                $arrayExecutable
            );
        }

        return $executableStruct;
    }
}
