<?php

use Amp\Injector\Application;
use Amp\Injector\Definitions;
use Amp\Injector\Injector;
use function Amp\Injector\any;
use function Amp\Injector\arguments;
use function Amp\Injector\names;
use function Amp\Injector\object;
use function Amp\Injector\singleton;
use function Amp\Injector\value;

require __DIR__ . "/../vendor/autoload.php";

class Singleton
{
    public stdClass $std;

    public function __construct(stdClass $std)
    {
        $this->std = $std;
    }
}

$stdClass = new stdClass;
$stdClass->foo = "foobar";

$definitions = (new Definitions)
    ->with(singleton(object(Singleton::class, arguments(names(['std' => value($stdClass)])))), 'hello_world');

$application = new Application(new Injector($definitions, any()));

$a = $application->getContainer()->get('hello_world');

print $a->std->foo . PHP_EOL;

$a->std->foo = 'baz';

// Note: Returns the same object
$a = $application->getContainer()->get('hello_world');

print $a->std->foo . PHP_EOL;
