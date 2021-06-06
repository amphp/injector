<?php

use Amp\Injector\Application;
use Amp\Injector\Definitions;
use Amp\Injector\Injector;
use Amp\Injector\ProviderContext;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface as PsrLogger;
use function Amp\Injector\automaticTypes;
use function Amp\Injector\factory;
use function Amp\Injector\object;

require __DIR__ . '/../vendor/autoload.php';

class Foo
{
    public function __construct(PsrLogger $logger)
    {
        $logger->info('Constructed.');
    }
}

class Bar
{
    public function __construct(PsrLogger $logger)
    {
        $logger->info('Constructed.');
    }
}

$logHandler = new StreamHandler(STDOUT);
$logHandler->pushProcessor(new PsrLogMessageProcessor);
$logHandler->setFormatter(new LineFormatter(null, null, true, true));

$logger = new Logger('main');
$logger->pushHandler($logHandler);

$definitions = (new Definitions)
    ->with(object(Foo::class))
    ->with(factory(function (ProviderContext $context) use ($logger): PsrLogger {
        $logger->info('Creating logger for ' . $context->getParameter(1));

        return $logger->withName($context->getParameter(1)?->getDeclaringClass() ?? 'unknown');
    }), 'logger');

$application = new Application(new Injector($definitions, automaticTypes($definitions)));
$application->getContainer()->get('logger')->info('Configuration complete.');

$foo = $application->invoke(factory(fn (Foo $foo) => $foo));
$bar = $application->invoke(object(Bar::class));
