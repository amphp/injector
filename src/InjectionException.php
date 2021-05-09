<?php

namespace Amp\Injector;

use Psr\Container\ContainerExceptionInterface;

class InjectionException extends \Exception implements ContainerExceptionInterface
{
    public function __construct(string $message = '', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
