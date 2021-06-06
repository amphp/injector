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

class A
{
    public $std;

    public function __construct(stdClass $std)
    {
        $this->std = $std;
    }
}

$stdClass = new stdClass;
$stdClass->foo = "foobar";

$definitions = (new Definitions)
    ->with(singleton(object(A::class, arguments()->with(names()->with('std', value($stdClass))))), 'a');

$application = new Application(new Injector($definitions, any()));

$a = $application->getContainer()->get('a');

print $a->std->foo . PHP_EOL;

$a->std->foo = 'baz';

$a = $application->getContainer()->get('a');

print $a->std->foo . PHP_EOL;
