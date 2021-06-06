<?php

use Amp\Injector\Application;
use Amp\Injector\Definitions;
use Amp\Injector\Injector;
use function Amp\Injector\any;
use function Amp\Injector\arguments;
use function Amp\Injector\factory;
use function Amp\Injector\names;
use function Amp\Injector\object;

require __DIR__ . "/../vendor/autoload.php";

class A
{
    private $a;
    private $b;

    public function __construct(stdClass $a, stdClass $b)
    {
        $this->a = $a;
        $this->b = $b;
    }

    public function print()
    {
        print \spl_object_id($this);
        print PHP_EOL;

        print $this->a->foo;
        print $this->b->foo;
        print PHP_EOL;
    }
}

$definitions = (new Definitions)
    ->with(object(
        A::class,
        arguments()->with(
        names()->with('a', factory(function () {
            $std = new stdClass;
            $std->foo = "foo";
            return $std;
        }))->with('b', factory(function () {
            $std = new stdClass;
            $std->foo = "bar";
            return $std;
        }))
    )
    ), 'a');

$application = new Application(new Injector($definitions, any()));

$a = $application->getContainer()->get('a');
$a->print();
