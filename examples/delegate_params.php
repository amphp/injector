<?php

use Amp\Injector\Argument\Delegate;
use Amp\Injector\ContextFactory;
use function Amp\Injector\arguments;

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

$contextFactory = new ContextFactory;
$contextFactory->prototype('a', A::class, arguments()->name('a', new Delegate(function () {
    $std = new stdClass;
    $std->foo = "foo";
    return $std;
}))->name('b', new Delegate(function () {
    $std = new stdClass;
    $std->foo = "bar";
    return $std;
})));

$context = $contextFactory->build();

$a = $context->get('a');
$a->print();

$a = $context->getType(A::class);
$a->print();
