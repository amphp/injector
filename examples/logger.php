<?php

use Amp\Injector\Context;
use Amp\Injector\ContextBuilder;
use Amp\Injector\InjectionException;
use Amp\Injector\Provider\DynamicProvider;
use Amp\Injector\Provider\ObjectProvider;
use function Amp\Injector\autowire;

require __DIR__ . '/../vendor/autoload.php';

class Logger
{
    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function log(string $message): void
    {
        print $this->name . ': ' . $message . PHP_EOL;
    }
}

class Foo
{
    public function __construct(Logger $logger)
    {
        $logger->log('Constructed');
    }
}

class Bar
{
    public function __construct(Logger $logger)
    {
        $logger->log('Constructed');
    }
}

$contextBuilder = new ContextBuilder;
$contextBuilder->add('foo', autowire(Foo::class));
$contextBuilder->primary(Logger::class, 'logger');
$contextBuilder->add('logger', new DynamicProvider(function (Context $context) {
    $dependents = $context->getDependents();
    if (!$dependents) {
        return new Logger('root');
    }

    $previous = array_key_last($dependents);
    $previousProvider = $context->getProvider($dependents[$previous]);
    if (!$previousProvider instanceof ObjectProvider) {
        throw new InjectionException('Unable to provide logger to anything other than ObjectProvider');
    }

    return new Logger($previousProvider->getType());
}));

$context = $contextBuilder->build();

$context->getType(Logger::class)->log('Configuration complete.');
$context->getType(Foo::class);

autowire(Bar::class)->get($context);