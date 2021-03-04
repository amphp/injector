<?php

namespace Amp\Injector;

class Noop
{
    public function noop()
    {
        // call-target, intentionally left empty
    }

    public function namedNoop($name)
    {
        // call-target, intentionally left empty
    }

    public function typehintedNoop(Noop $noop)
    {
        // call-target, intentionally left empty
    }
}
