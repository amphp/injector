<?php

namespace Amp\Injector\Internal;

use Kelunik\FiberLocal\FiberLocal;

/** @internal */
final class FiberLocalObjectSet
{
    private FiberLocal $set;

    public function __construct()
    {
        $this->set = FiberLocal::withInitial(static fn () => []);
    }

    public function add(object $value): void
    {
        $set = $this->set->get();
        $set[\spl_object_id($value)] = $value;

        $this->set->set($set);
    }

    public function remove(object $value): void
    {
        $set = $this->set->get();
        unset($set[\spl_object_id($value)]);

        $this->set->set($set);
    }

    public function contains(object $value): bool
    {
        return isset($this->set->get()[\spl_object_id($value)]);
    }
}
