<?php

use Amp\Injector\ContextBuilder;
use Amp\Injector\Provider\DynamicProvider;
use function Amp\Injector\arguments;
use function Amp\Injector\autowire;

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

$contextFactory = new ContextBuilder;
$contextFactory->add('a', autowire(A::class, arguments()->name('a', new DynamicProvider(function () {
    $std = new stdClass;
    $std->foo = "foo";
    return $std;
}))->name('b', new DynamicProvider(function () {
    $std = new stdClass;
    $std->foo = "bar";
    return $std;
}))));

$context = $contextFactory->build();

$a = $context->get('a');
$a->print();

$a = $context->getType(A::class);
$a->print();
