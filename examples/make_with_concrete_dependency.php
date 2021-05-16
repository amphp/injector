<?php

use Amp\Injector\ContextBuilder;
use Amp\Injector\Provider\Value;
use function Amp\Injector\arguments;
use function Amp\Injector\autowire;
use function Amp\Injector\singleton;

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

$contextBuilder = new ContextBuilder;
$contextBuilder->add('a', singleton(autowire(A::class, arguments()->name('std', new Value($stdClass)))));

$context = $contextBuilder->build();

$a = $context->getType(A::class);

print $a->std->foo . PHP_EOL;
