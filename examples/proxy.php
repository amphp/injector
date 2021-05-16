<?php

use Amp\Injector\Context;
use Amp\Injector\ContextBuilder;
use Amp\Injector\Provider;
use Amp\Injector\Provider\ObjectProvider;
use ProxyManager\Factory\LazyLoadingValueHolderFactory;
use function Amp\Injector\arguments;
use function Amp\Injector\autowire;
use function Amp\Injector\value;

require __DIR__ . '/../vendor/autoload.php';

interface Engine
{
    public function turnOn();
}

class V8 implements Engine
{
    public $value = '';

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

function proxy(ObjectProvider $provider): Provider
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

$contextBuilder = new ContextBuilder;
$contextBuilder->add('car', proxy(autowire(Car::class)));
$contextBuilder->add('engine', proxy(autowire(V8::class, arguments()->name('arg', value('some text')))));

// TODO: Replacement for prepare?
// $contextBuilder->prepare(V8::class, function (V8 $v8, Amp\Injector\Injector $injector) {
//     $v8->value = 42;
// });

print 'Configuration complete.' . PHP_EOL;

$car = $contextBuilder->build()->getType(Car::class);

print '$car is an instance of Car: ';
\var_dump($car instanceof Car);

print 'Note: Constructor of Car has not been called, yet.' . PHP_EOL;

print 'turnRight call the Car constructor.' . PHP_EOL;

$car->turnRight();

print 'turnLeft call the V8 constructor.' . PHP_EOL;

$car->turnLeft();

$car->turnLeft();
