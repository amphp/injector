<?php

use Amp\Injector\Argument\Value;
use Amp\Injector\ContextFactory;
use function Amp\Injector\arguments;

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

$contextFactory = new ContextFactory;
$contextFactory->singleton(A::class, A::class, arguments()->name('std', new Value($stdClass)));

$context = $contextFactory->build();

$a = $context->getType(A::class);

print $a->std->foo . PHP_EOL;
