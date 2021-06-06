<?php

use Amp\Injector\Application;
use Amp\Injector\Definition;
use Amp\Injector\Definitions;
use Amp\Injector\Injector;
use Amp\Injector\Meta\Type;
use Amp\Injector\Provider;
use Amp\Injector\ProviderContext;
use ProxyManager\Factory\LazyLoadingValueHolderFactory;
use function Amp\Injector\arguments;
use function Amp\Injector\automaticTypes;
use function Amp\Injector\factory;
use function Amp\Injector\names;
use function Amp\Injector\object;
use function Amp\Injector\value;

require __DIR__ . '/../vendor/autoload.php';

interface Engine
{
    public function turnOn();
}

class V8 implements Engine
{
    public string $value = '';

    public function __construct(string $arg)
    {
        print __METHOD__ . PHP_EOL;
    }

    public function turnOn(): void
    {
        print __METHOD__ . PHP_EOL;
    }
}

class Car
{
    private Engine $engine;

    public function __construct(Engine $engine)
    {
        $this->engine = $engine;

        print __METHOD__ . PHP_EOL;
    }

    public function turnRight(): void
    {
        print __METHOD__ . PHP_EOL;
    }

    public function turnLeft(): void
    {
        $this->engine->turnOn();

        print __METHOD__ . PHP_EOL;
        print $this->engine->value . PHP_EOL;
    }
}

function proxy(string $class, Definition $definition): Definition
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

$definitions = (new Definitions)
    ->with(proxy(Car::class, object(Car::class)), 'car')
    ->with(proxy(V8::class, object(V8::class, arguments()->with(names()->with('arg', value('some text'))))), 'engine');

// TODO: Replacement for prepare?
// $contextBuilder->prepare(V8::class, function (V8 $v8, Amp\Injector\Injector $injector) {
//     $v8->value = 42;
// });

print 'Configuration complete.' . PHP_EOL;

$application = new Application(new Injector($definitions, automaticTypes($definitions)));

$car = $application->getContainer()->get('car');

print '$car is an instance of Car: ';
\var_dump($car instanceof Car);

print 'Note: Constructor of Car has not been called, yet.' . PHP_EOL;

print 'turnRight call the Car constructor.' . PHP_EOL;

$car->turnRight();

print 'turnLeft call the V8 constructor.' . PHP_EOL;

$car->turnLeft();

$car->turnLeft();
