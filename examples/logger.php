<?php

use Amp\Injector\Application;
use Amp\Injector\Definition\ProviderDefinition;
use Amp\Injector\Injector;
use Amp\Injector\Provider\ContextProvider;
use Amp\Injector\ProviderContext;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface as PsrLogger;
use function Amp\Injector\any;
use function Amp\Injector\automaticTypes;
use function Amp\Injector\definitions;
use function Amp\Injector\factory;
use function Amp\Injector\object;
use function Amp\Injector\types;

require __DIR__ . '/../vendor/autoload.php';

class Foo
{
    public function __construct(PsrLogger $logger)
    {
        $logger->info('Constructed');
    }
}

class Bar
{
    public function __construct(PsrLogger $logger)
    {
        $logger->info('Constructed');
    }
}

$logHandler = new StreamHandler(STDOUT);
$logHandler->pushProcessor(new PsrLogMessageProcessor);
$logHandler->setFormatter(new LineFormatter);

$logger = new Logger('main');
$logger->pushHandler($logHandler);

$definitions = definitions()
    ->with(object(Foo::class))
    ->with(factory(function (ProviderContext $context) use ($logger): PsrLogger {
        // Note the return type to automatically provide that type -- ^^^^^^^^^

        if ($parameter = $context->getParameter(1)) {
            $logger->info('Creating logger for ' . $parameter);

            return $logger->withName($parameter->getDeclaringClass() ?? 'unknown');
        }

        $logger->info('Using default logger');

        return $logger;
    }), 'logger');

$application = new Application(new Injector(any(
    types()->with(ProviderContext::class, new ProviderDefinition(new ContextProvider)),
    automaticTypes($definitions),
)), $definitions);

$application->getContainer()->get('logger')->info('Configuration complete');

// Using invoke and a factory callable let's you specify type, name, etc.
$foo = $application->invoke(factory(fn (Foo $foo) => $foo));

// We can also provide objects not defined in the initial set of definitions
$bar = $application->invoke(object(Bar::class));
