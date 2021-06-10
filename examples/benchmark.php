<?php

use Amp\Injector\Application;
use Amp\Injector\Injector;
use Amp\Injector\Noop;
use function Amp\Injector\arguments;
use function Amp\Injector\automaticTypes;
use function Amp\Injector\definitions;
use function Amp\Injector\factory;
use function Amp\Injector\names;
use function Amp\Injector\object;
use function Amp\Injector\value;

require __DIR__ . '/../vendor/autoload.php';

$definitions = definitions()->with(object(\stdClass::class));
$injector = new Injector(automaticTypes($definitions));
$application = new Application($injector, $definitions);

for ($i = 0; $i < 10000; $i++) {
    $application->invoke(factory(\Closure::fromCallable([new Noop, 'namedNoop']), arguments(names(['name' => value('foo')]))));
}
