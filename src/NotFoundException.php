<?php

namespace Amp\Injector;

use Psr\Container\NotFoundExceptionInterface;

final class NotFoundException extends InjectionException implements NotFoundExceptionInterface
{

}