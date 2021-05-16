<?php

namespace Amp\Injector\Provider;

use Amp\Injector\Context;
use Amp\Injector\Executable;
use Amp\Injector\InjectionException;
use Amp\Injector\Provider;

final class Unknown implements Provider
{
    private int $index;
    private Executable $executable;

    public function __construct(int $index, Executable $executable)
    {
        $this->index = $index;
        $this->executable = $executable;
    }

    public function get(Context $context): mixed
    {
        $name = $this->executable->getNameByPosition($this->index);

        throw new InjectionException('Failed to provide argument #' . $this->index . ' ($' . $name . '), because no definition exists to provide it');
    }

    public function getType(): ?string
    {
        return null;
    }

    public function getDependencies(Context $context): array
    {
        return [];
    }
}